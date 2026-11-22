<?php

namespace App\Service\Trainers;

use App\Models\CourseSession;
use App\Models\DailyPlan;
use App\Models\Homework;
use App\Models\User;
use App\Support\Text;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

/**
 * «План на сегодня» в «Качалке» (правила — docs/trainers/План дня —
 * механика.md): посильная норма тренажёров на день под выбранные учеником
 * минуты. План собирается при первом заходе за день (TrainerHub) и до
 * конца дня не растёт; сборка — DailyPlanner, здесь — данные для неё,
 * снимок в daily_plans и отметки «сделано».
 *
 * Пока пункт плана не сделан, сессия тренажёра не больше его квоты —
 * тренажёры спрашивают quota() при старте. Сделан — тренажёр снова
 * работает как обычно: дальше ученик решает сам.
 *
 * Привязан как scoped (AppServiceProvider): план дня читается один раз
 * за запрос, его спрашивают и хаб, и все тренажёры.
 */
class DailyPlanService
{
    /** @var array<string, ?DailyPlan> «user|день» => план */
    private array $stored = [];

    /** @var array<string, array> «user|тренажёр» => planSessions() */
    private array $sessions = [];

    public function __construct(private DailyPlanner $planner)
    {
    }

    public function today(): string
    {
        return now()->toDateString();
    }

    /**
     * Сколько карточек дать в сессии тренажёра, пока его пункт плана не
     * сделан; null — без ограничения (не в плане, сделан, норма не выбрана).
     *
     * @param  string  $kind  review / learn / session
     */
    public function quota(User $user, string $card, string $kind): ?int
    {
        $plan = $this->stored($user);
        foreach ($plan?->items ?? [] as $item) {
            if ($item['key'] === $card && $item['kind'] === $kind && empty($item['done_at']) && empty($item['gone'])
                && $item['situation'] !== Trainer::UNFINISHED) {
                return max(1, (int) $item['quota']);
            }
        }

        return null;
    }

    /**
     * План на сегодня по статусам тренажёров из TrainerHub: собирает, если
     * его ещё нет, и отмечает сделанное.
     *
     * @param  array  $trainers  TrainerHub: [{trainer, title, url, status}]
     * @return ?array null — норма не выбрана; иначе {norm, minutes, light,
     *                items: [{key, kind, situation, quota, title, hall_tab, url, quest, done}],
     *                done, total, complete}
     */
    public function sync(User $user, array $trainers): ?array
    {
        $norm = (int) $user->trainer_daily_minutes;
        if ($norm <= 0) {
            return null;
        }

        $byKey = [];
        foreach ($trainers as $t) {
            $byKey[get_class($t['trainer'])] = $t;
        }

        $plan = $this->stored($user);
        if (! $plan) {
            $plan = $this->create($user, $norm, $byKey);
        } elseif ($plan->stale) {
            $this->refill($plan, $user, $byKey);
        }

        if ($plan->exists) {
            $this->markDone($plan, $user, $byKey);
        }

        return $this->view($plan, $byKey, $norm);
    }

    /**
     * Ученик выбрал норму. Сегодняшний план, если он есть, пересобирается:
     * сделанное остаётся, несделанное — под новый бюджет (на следующем
     * показе, по свежим статусам — см. refill()).
     */
    public function setNorm(User $user, int $minutes): void
    {
        $user->forceFill(['trainer_daily_minutes' => $minutes])->save();

        $plan = $this->stored($user);
        if ($plan) {
            $plan->items = array_values(array_filter($plan->items, fn ($i) => ! empty($i['done_at']) && empty($i['gone'])));
            $plan->minutes = $this->budgetMinutes($minutes, $plan->light);
            $plan->stale = true;
            $plan->completed_at = null;
            $plan->save();
        }

        unset($this->stored[$this->memoKey($user)]);
    }

    /*
    |--------------------------------------------------------------------------
    | Сборка
    |--------------------------------------------------------------------------
    */

    private function create(User $user, int $norm, array $byKey): DailyPlan
    {
        $light = $this->lightReason($user);
        $minutes = $this->budgetMinutes($norm, $light);
        $items = $this->stamp($this->compose($user, $byKey, $minutes * 60, $this->slots($user), []));

        $plan = new DailyPlan([
            'user_id' => $user->id,
            'day' => $this->today(),
            'minutes' => $minutes,
            'light' => $light,
            'items' => $items,
        ]);

        // Пустой план (делать сегодня нечего) не храним: если что-то
        // появится позже в тот же день, план соберётся тогда.
        if (! $items) {
            return $plan;
        }

        try {
            $plan->save();
        } catch (QueryException $e) {
            // Две вкладки собрали план одновременно — берём сохранённый.
            $plan = DailyPlan::where('user_id', $user->id)->whereDate('day', $this->today())->firstOrFail();
        }

        return $this->stored[$this->memoKey($user)] = $plan;
    }

    /** Норму поменяли: к сделанному добираем пункты на остаток бюджета. */
    private function refill(DailyPlan $plan, User $user, array $byKey): void
    {
        $done = $plan->items;
        $spent = array_sum(array_column($done, 'sec'));
        $budget = $plan->minutes * 60 - $spent;
        $slots = $this->slots($user) - count($done);

        // Места кончились, а время осталось — compose() доберёт тренажёры.
        $new = $budget > 0
            ? $this->stamp($this->compose($user, $byKey, $budget, max(0, $slots), array_column($done, 'key')))
            : [];

        $plan->items = array_merge($done, $new);
        $plan->stale = false;
        $plan->completed_at = $plan->items && ! $new ? now() : null;
        $plan->save();
    }

    private function compose(User $user, array $byKey, int $budget, int $slots, array $exclude): array
    {
        $settings = config('daily_plan.trainers', []);
        $halls = $this->halls();
        $last = $this->lastPlanned($user);

        $candidates = [];
        foreach ($byKey as $key => $t) {
            $status = $t['status'];
            $kind = $status['kind'] ?? null;
            $conf = $settings[$key][$kind] ?? null;
            if (in_array($key, $exclude, true) || $conf === null) {
                continue;
            }

            $count = (int) $status['count'];
            if ($status['situation'] !== Trainer::UNFINISHED) {
                $count = min($count, (int) config($conf['max']));
            }

            $candidates[] = [
                'key' => $key,
                'hall' => $halls[$key]['key'] ?? 'other',
                'situation' => $status['situation'],
                'kind' => $kind,
                'count' => $count,
                'sec' => $this->pace($user, $key, $kind, (float) $conf['sec']),
                'min' => (int) $conf['min'],
                'last' => $last[$key] ?? null,
            ];
        }

        return $this->planner->plan(
            $candidates,
            $budget,
            $slots,
            (float) config('daily_plan.backlog_factor'),
            60 * (int) config('daily_plan.extra.from_minutes'),
            (int) config('daily_plan.extra.max'),
        );
    }

    /** Время добавления пункта: «сделано» — только сессия, законченная после него. */
    private function stamp(array $items): array
    {
        $at = now()->toIso8601String();

        return array_map(fn ($i) => $i + ['at' => $at, 'done_at' => null], $items);
    }

    private function budgetMinutes(int $norm, ?string $light): int
    {
        if ($light === null) {
            return $norm;
        }

        return max((int) config('daily_plan.light.min_minutes'), (int) round($norm * (float) config('daily_plan.light.factor')));
    }

    /**
     * Темп ученика в тренажёре: медиана секунд на карточку по последним
     * сессиям этого вида. Нет истории — темп по умолчанию.
     */
    private function pace(User $user, string $key, string $kind, float $default): float
    {
        $conf = config('daily_plan.pace');
        $values = [];
        foreach ($this->sessionsOf($user, $key) as $s) {
            if ($s['kind'] !== $kind || $s['items'] <= 0) {
                continue;
            }
            $per = $s['seconds'] / $s['items'];
            if ($per > 0 && $per <= $default * $conf['outlier_factor']) {
                $values[] = $per;
            }
            if (count($values) >= $conf['sample_size']) {
                break;
            }
        }

        if (! $values) {
            return $default;
        }

        sort($values);
        $mid = intdiv(count($values), 2);
        $median = count($values) % 2 ? $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;

        return min($default * $conf['clamp'], max($default / $conf['clamp'], $median));
    }

    /** 1 курс — 2 тренажёра, 2 курса — 3 (config('daily_plan.slots')). */
    private function slots(User $user): int
    {
        $map = config('daily_plan.slots');
        $n = max(1, min($this->activeCourseIds($user)->count(), max(array_keys($map))));

        return (int) $map[$n];
    }

    /** lesson — урок сегодня, homework — скоро сдавать домашку, null — обычный день. */
    private function lightReason(User $user): ?string
    {
        $courses = $this->activeCourseIds($user);
        if ($courses->isEmpty()) {
            return null;
        }

        $lesson = CourseSession::query()->visible()
            ->whereIn('course_id', $courses)
            ->whereDate('date', $this->today())
            ->exists();
        if ($lesson) {
            return 'lesson';
        }

        $homework = Homework::query()
            ->whereIn('course_id', $courses)
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [now(), now()->addHours((int) config('daily_plan.light.homework_hours'))])
            ->whereDoesntHave('submissions', fn ($q) => $q->where('user_id', $user->id)->where('status', '!=', 'in_progress'))
            ->exists();

        return $homework ? 'homework' : null;
    }

    private function activeCourseIds(User $user)
    {
        return $user->courses()
            ->wherePivot('status', 'active')
            ->where(fn ($q) => $q->whereNull('course_user.expires_at')->orWhere('course_user.expires_at', '>=', now()))
            ->pluck('courses.id');
    }

    /** @return array<string, string> тренажёр => последний день в плане (Y-m-d) */
    private function lastPlanned(User $user): array
    {
        $last = [];
        $plans = DailyPlan::where('user_id', $user->id)
            ->whereDate('day', '<', $this->today())
            ->whereDate('day', '>=', now()->subDays(14)->toDateString())
            ->orderBy('day')
            ->get(['day', 'items']);

        foreach ($plans as $plan) {
            foreach ($plan->items as $item) {
                $last[$item['key']] = $plan->day->toDateString();
            }
        }

        return $last;
    }

    /*
    |--------------------------------------------------------------------------
    | Учёт сделанного
    |--------------------------------------------------------------------------
    */

    /**
     * Пункт сделан, когда после его добавления закончилась сегодняшняя
     * сессия этого вида, или делать в тренажёре этим видом больше нечего.
     * Начатая сессия того же вида — ещё не сделан.
     */
    private function markDone(DailyPlan $plan, User $user, array $byKey): void
    {
        $today = $this->today();
        $items = $plan->items;
        $changed = false;

        foreach ($items as &$item) {
            if (! empty($item['done_at']) || ! empty($item['gone'])) {
                continue;
            }

            $t = $byKey[$item['key']] ?? null;
            if ($t === null) {
                // Доступ к тренажёру закрыли — пункт просто убираем.
                $item['gone'] = true;
                $changed = true;

                continue;
            }

            $status = $t['status'];
            $kind = $status['kind'] ?? null;
            if ($status['situation'] === Trainer::UNFINISHED && $kind === $item['kind']) {
                continue;
            }

            $since = Carbon::parse($item['at']);
            $finished = false;
            foreach ($this->sessionsOf($user, $item['key']) as $s) {
                if ($s['kind'] === $item['kind'] && $s['day'] === $today && $s['finished_at']->gte($since)) {
                    $finished = true;
                    break;
                }
            }

            if ($finished || $status['situation'] === Trainer::DONE || $kind !== $item['kind']) {
                $item['done_at'] = now()->toIso8601String();
                $changed = true;
            }
        }
        unset($item);

        if (! $changed) {
            return;
        }

        $plan->items = $items;
        $left = array_filter($items, fn ($i) => empty($i['done_at']) && empty($i['gone']));
        if (! $left && ! $plan->completed_at) {
            $plan->completed_at = now();
        }
        $plan->save();
    }

    /*
    |--------------------------------------------------------------------------
    | Показ
    |--------------------------------------------------------------------------
    */

    private function view(DailyPlan $plan, array $byKey, int $norm): array
    {
        $halls = $this->halls();
        $items = [];
        $sec = 0;

        foreach ($plan->items as $item) {
            $t = $byKey[$item['key']] ?? null;
            if ($t === null || ! empty($item['gone'])) {
                continue;
            }
            $sec += (int) $item['sec'];
            $done = ! empty($item['done_at']);

            $items[] = [
                'key' => $item['key'],
                'kind' => $item['kind'],
                'situation' => $item['situation'],
                'quota' => (int) $item['quota'],
                'title' => $t['title'],
                'hall_tab' => $halls[$item['key']]['tab'] ?? null,
                'url' => $t['url'],
                'quest' => $this->quest($item, $t['trainer']->words()),
                'done' => $done,
            ];
        }

        $doneCount = count(array_filter($items, fn ($i) => $i['done']));

        return [
            'norm' => $norm,
            // Сколько займёт по темпу ученика — бывает меньше нормы, если
            // делать сегодня почти нечего.
            'minutes' => max(1, (int) ceil($sec / 60)),
            'light' => $plan->light,
            'items' => $items,
            'done' => $doneCount,
            'total' => count($items),
            'complete' => $items && $doneCount === count($items),
        ];
    }

    private function quest(array $item, array $words): string
    {
        $n = (int) $item['quota'];
        $what = Text::plural($n, $words['what']);

        if ($item['situation'] === Trainer::UNFINISHED) {
            return 'Доделать: '.$what;
        }

        return match ($item['kind']) {
            'review' => 'Повторить '.$what,
            'learn' => 'Выучить '.Text::plural($n, $words['new_what']),
            default => in_array($item['situation'], [Trainer::REVIEW, Trainer::OVERDUE], true)
                ? 'Повторить '.$what
                : ($words['do'] ?? 'Пройти').' '.$what,
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Мелочи
    |--------------------------------------------------------------------------
    */

    private function stored(User $user): ?DailyPlan
    {
        $key = $this->memoKey($user);
        if (! array_key_exists($key, $this->stored)) {
            $this->stored[$key] = DailyPlan::where('user_id', $user->id)->whereDate('day', $this->today())->first();
        }

        return $this->stored[$key];
    }

    private function memoKey(User $user): string
    {
        return $user->id.'|'.$this->today();
    }

    private function sessionsOf(User $user, string $key): array
    {
        $memo = $user->id.'|'.$key;
        if (! isset($this->sessions[$memo])) {
            $this->sessions[$memo] = app($key)->planSessions($user, 2 * (int) config('daily_plan.pace.sample_size'));
        }

        return $this->sessions[$memo];
    }

    /** @return array<string, array{key: string, tab: string}> тренажёр => зал */
    private function halls(): array
    {
        $out = [];
        foreach (config('trainers.halls', []) as $hallKey => $hall) {
            foreach ($hall['stations'] as $station) {
                if (! empty($station['trainer'])) {
                    $out[$station['trainer']] = ['key' => $hallKey, 'tab' => $hall['tab']];
                }
            }
        }

        return $out;
    }
}
