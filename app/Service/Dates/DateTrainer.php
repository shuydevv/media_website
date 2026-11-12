<?php

namespace App\Service\Dates;

use App\Service\Demo\DemoMode;
use App\Models\HistEvent;
use App\Models\HistFact;
use App\Models\HistSession;
use App\Models\User;
use App\Models\UserHistEvent;
use App\Service\FishFoodService;
use App\Service\Terms\DistractorPicker;
use App\Service\Terms\SessionReward;
use App\Service\Terms\TermSchedule;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Сессии тренажёра дат по истории. Механика — та же, что у тренажёра
 * терминов (App\Service\Terms\TermTrainer, там же подробные комментарии к
 * каждому правилу); здесь отличия:
 *
 *  - элемент тренажёра — событие или правление (HistEvent::kind), они
 *    изучаются и повторяются вперемешку;
 *  - способ А — ввод года (у диапазона — года начала и окончания), а не
 *    названия; подсказка открывает цифры, опечатки не прощаются;
 *  - неверные варианты в квизе и «лишний факт» берутся только у элементов
 *    того же вида: факт о правлении среди фактов о битве угадывается без
 *    знаний. Правления, пересекающиеся по годам (царь и регент,
 *    соправители), друг другу неверных вариантов не дают — их факты могут
 *    быть верны для обоих.
 *
 * Расписание, корм и подбор неверных вариантов — общие классы тренажёра
 * терминов (TermSchedule, SessionReward, DistractorPicker) со своими числами
 * из config/dates.php.
 *
 * Состояние сессии (hist_sessions.state) устроено как у терминов, только
 * id элемента лежит в 't' и 'event_id'.
 */
class DateTrainer
{
    private TermSchedule $schedule;

    private SessionReward $reward;

    private YearAnswer $years;

    private DistractorPicker $picker;

    public function __construct(private FishFoodService $fish)
    {
        $this->schedule = new TermSchedule(config('dates.intervals'));
        $this->reward = new SessionReward(config('dates.corm'));
        $this->years = new YearAnswer();
        $this->picker = new DistractorPicker();
    }

    public function today(): string
    {
        return now()->toDateString();
    }

    /*
    |--------------------------------------------------------------------------
    | Что доступно сегодня
    |--------------------------------------------------------------------------
    */

    /**
     * Состояние трёх карточек на главной тренажёра — те же статусы, что у
     * TermTrainer::overview().
     */
    public function overview(User $user): array
    {
        $today = $this->today();

        $dueCount = $this->dueQuery($user, $today)->count();
        $learnedCount = $this->learnedQuery($user)->count();

        $reviewToday = $this->sessionOf($user, HistSession::KIND_REVIEW, $today);
        $learnToday = $this->sessionOf($user, HistSession::KIND_LEARN, $today);
        $freeOpen = HistSession::where('user_id', $user->id)
            ->where('kind', HistSession::KIND_FREE)
            ->whereNull('finished_at')
            ->latest('id')
            ->first();

        $limit = (int) config('dates.daily_review_limit');

        if ($reviewToday && !$reviewToday->finished_at) {
            $review = ['status' => 'in_progress', 'count' => $this->remainingCount($reviewToday)];
        } elseif ($reviewToday) {
            $review = ['status' => 'done', 'count' => 0, 'carried' => $dueCount, 'next' => $this->nextReview($user, $today)];
        } elseif ($dueCount > 0) {
            $review = ['status' => 'available', 'count' => min($dueCount, $limit), 'carried' => max(0, $dueCount - $limit)];
        } else {
            $review = ['status' => 'empty', 'count' => 0, 'next' => $this->nextReview($user, $today)];
        }

        $batch = $this->nextBatch($user);
        if ($learnToday && !$learnToday->finished_at) {
            $learn = ['status' => 'in_progress', 'count' => $this->remainingCount($learnToday)];
        } elseif ($learnToday) {
            $learn = ['status' => 'done_today', 'count' => 0];
        } elseif ($batch->isEmpty()) {
            $learn = ['status' => 'all_learned', 'count' => 0];
        } elseif ($dueCount > 0) {
            $learn = ['status' => 'blocked_due', 'count' => $batch->count()];
        } else {
            $learn = ['status' => 'available', 'count' => $batch->count()];
        }

        if ($freeOpen) {
            $free = ['status' => 'in_progress', 'count' => $this->remainingCount($freeOpen), 'total' => $this->totalCount($freeOpen)];
        } elseif ($learnedCount === 0) {
            $free = ['status' => 'empty', 'count' => 0];
        } elseif ($dueCount > 0 && !($reviewToday && $reviewToday->finished_at)) {
            $free = ['status' => 'blocked_review', 'count' => $learnedCount];
        } else {
            $free = ['status' => 'available', 'count' => $learnedCount];
        }

        return compact('review', 'learn', 'free', 'dueCount', 'learnedCount');
    }

    /**
     * Следующая порция: самый лёгкий уровень, где есть неизученное; внутри —
     * следующие по sort_order. Порядок задаёт автор в поле order файла базы:
     * сначала главное, в конце второстепенное, периоды в порции вперемешку.
     * Раньше периоды перебирались по кругу по годам — и в первой же порции
     * оказывался Даниил Московский.
     */
    public function nextBatch(User $user)
    {
        $learned = UserHistEvent::where('user_id', $user->id)->pluck('event_id');
        // Демо-ученик изучает только лёгкий набор (config/demo.php); неверные
        // варианты и соответствия — по-прежнему из всей базы, иначе рядом
        // с Лениным оказались бы Пётр I и Кутузов.
        $base = DemoMode::restrict(HistEvent::active()->whereNotIn('id', $learned), 'dates', 'slug');

        $level = (int) (clone $base)->min('level');

        return $base->where('level', $level)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit((int) config('dates.batch_size'))
            ->get();
    }

    /** @return array{date: Carbon, count: int}|null */
    public function nextReview(User $user, string $today): ?array
    {
        $date = $this->learnedQuery($user)->whereDate('user_hist_events.due_on', '>', $today)->min('user_hist_events.due_on');
        if ($date === null) {
            return null;
        }

        $date = Carbon::parse($date)->startOfDay();

        return ['date' => $date, 'count' => $this->dueQuery($user, $date->toDateString())->count()];
    }

    public function dueTomorrow(User $user): int
    {
        return $this->dueQuery($user, now()->addDay()->toDateString())->count();
    }

    /** См. TermTrainer::doneCount(). */
    public function doneCount(HistSession $session): int
    {
        $state = $session->state;
        $done = count($state['done'] ?? []);
        $current = $state['current'] ?? null;
        $succeeded = match ($current['type'] ?? null) {
            'a' => $session->kind !== HistSession::KIND_LEARN && ($current['result']['correct'] ?? false),
            'b', 'c' => (bool) ($current['done'] ?? false),
            default => false,
        };

        return $done + ($succeeded ? 1 : 0);
    }

    public function totalCount(HistSession $session): int
    {
        return $session->kind === HistSession::KIND_LEARN
            ? count($session->state['terms'] ?? [])
            : (int) $session->total;
    }

    public function remainingCount(HistSession $session): int
    {
        return max(0, $this->totalCount($session) - $this->doneCount($session));
    }

    public function overdueCount(User $user): int
    {
        return $this->learnedQuery($user)->whereDate('user_hist_events.due_on', '<', $this->today())->count();
    }

    public function nearNextLevel(User $user): bool
    {
        $learned = UserHistEvent::where('user_id', $user->id)->pluck('event_id');
        // Демо-ученик изучает только лёгкий набор (config/demo.php); неверные
        // варианты и соответствия — по-прежнему из всей базы, иначе рядом
        // с Лениным оказались бы Пётр I и Кутузов.
        $base = DemoMode::restrict(HistEvent::active()->whereNotIn('id', $learned), 'dates', 'slug');
        $level = (clone $base)->min('level');
        if ($level === null || !(clone $base)->where('level', '>', $level)->exists()) {
            return false;
        }

        return (clone $base)->where('level', $level)->count() <= 2 * (int) config('dates.batch_size');
    }

    private function dueQuery(User $user, string $date)
    {
        return $this->learnedQuery($user)->whereDate('user_hist_events.due_on', '<=', $date);
    }

    private function learnedQuery(User $user)
    {
        return UserHistEvent::query()
            ->join('hist_events', 'hist_events.id', '=', 'user_hist_events.event_id')
            ->where('hist_events.is_active', true)
            ->where('user_hist_events.user_id', $user->id);
    }

    private function sessionOf(User $user, string $kind, string $day): ?HistSession
    {
        return HistSession::where('user_id', $user->id)
            ->where('kind', $kind)
            ->whereDate('day', $day)
            ->latest('id')
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | Запуск
    |--------------------------------------------------------------------------
    */

    /**
     * Продолжает незаконченную сессию этого вида или создаёт новую.
     *
     * @throws DomainException если сессия этого вида сейчас недоступна
     */
    public function start(User $user, string $kind): HistSession
    {
        return DB::transaction(function () use ($user, $kind) {
            User::whereKey($user->id)->lockForUpdate()->first();

            $today = $this->today();
            $this->closeStale($user, $today);

            $status = $this->overview($user)[$kind]['status'] ?? null;

            if ($status === 'in_progress') {
                return $kind === HistSession::KIND_FREE
                    ? HistSession::where('user_id', $user->id)->where('kind', $kind)->whereNull('finished_at')->latest('id')->first()
                    : $this->sessionOf($user, $kind, $today);
            }
            if ($status !== 'available') {
                throw new DomainException('Эта сессия сейчас недоступна.');
            }

            [$state, $total] = match ($kind) {
                HistSession::KIND_LEARN => $this->newLearnState($user),
                HistSession::KIND_REVIEW => $this->newReviewState($user, $today),
                HistSession::KIND_FREE => $this->newFreeState($user),
            };

            return HistSession::create([
                'user_id' => $user->id,
                'kind' => $kind,
                'day' => $today,
                'state' => $state,
                'total' => $total,
                'correct_first' => 0,
            ]);
        });
    }

    /** Вчерашние незаконченные изучение и повторение закрываются без корма. */
    private function closeStale(User $user, string $today): void
    {
        HistSession::where('user_id', $user->id)
            ->whereIn('kind', [HistSession::KIND_LEARN, HistSession::KIND_REVIEW])
            ->whereNull('finished_at')
            ->whereDate('day', '<', $today)
            ->update(['finished_at' => now(), 'corm_awarded' => 0]);
    }

    private function newLearnState(User $user): array
    {
        $ids = $this->nextBatch($user)->pluck('id')->map(fn ($id) => (int) $id)->all();

        return [[
            'terms' => $ids,
            'queue' => array_map(fn (int $id) => ['t' => $id, 's' => 'card'], $ids),
            'quiz' => $this->alternateQuiz($ids),
            'first' => ['a' => [], 'b' => []],
            'done' => [],
            'current' => null,
        ], count($ids) * 2];
    }

    private function newReviewState(User $user, string $today): array
    {
        $rows = $this->dueQuery($user, $today)
            ->orderBy('user_hist_events.due_on')
            ->orderBy('user_hist_events.step')
            ->orderBy('user_hist_events.id')
            ->limit((int) config('dates.daily_review_limit'))
            ->get(['user_hist_events.event_id', 'user_hist_events.next_method']);

        return [$this->queueState($rows), $rows->count()];
    }

    private function newFreeState(User $user): array
    {
        $seenToday = HistSession::where('user_id', $user->id)
            ->where('kind', HistSession::KIND_FREE)
            ->whereDate('day', $this->today())
            ->get()
            ->flatMap(fn (HistSession $s) => array_keys($s->state['first'] ?? []))
            ->flip();

        $rows = $this->learnedQuery($user)
            ->get(['user_hist_events.event_id', 'user_hist_events.next_method', 'user_hist_events.step', 'user_hist_events.last_reviewed_on'])
            ->shuffle()
            ->sortBy(fn ($row) => [
                $seenToday->has($row->event_id) ? 1 : 0,
                (int) $row->step,
                (string) $row->last_reviewed_on,
            ])
            ->take((int) config('dates.free_session_limit'))
            ->values();

        return [$this->queueState($rows), $rows->count()];
    }

    private function queueState($rows): array
    {
        $quiz = $this->alternateQuiz(
            $rows->filter(fn ($row) => $row->next_method !== UserHistEvent::METHOD_YEAR)->pluck('event_id')->all()
        );

        return [
            'queue' => $rows->map(fn ($row) => [
                't' => (int) $row->event_id,
                's' => $row->next_method === UserHistEvent::METHOD_YEAR ? 'a' : $quiz[(int) $row->event_id],
            ])->all(),
            'quiz' => $quiz,
            'first' => [],
            'done' => [],
            'current' => null,
        ];
    }

    /** @return array<int, string> id элемента => 'b' | 'c' */
    private function alternateQuiz(array $ids): array
    {
        $kinds = random_int(0, 1) ? ['b', 'c'] : ['c', 'b'];
        $quiz = [];
        foreach (array_values($ids) as $i => $id) {
            $quiz[(int) $id] = $kinds[$i % 2];
        }

        return $quiz;
    }

    /*
    |--------------------------------------------------------------------------
    | Действия ученика
    |--------------------------------------------------------------------------
    */

    public function prepare(HistSession $session): HistSession
    {
        return $this->mutate($session, fn () => null);
    }

    /** Карточка знакомства: «Запомнил» / «Ещё не запомнил». */
    public function card(HistSession $session, bool $remembered): HistSession
    {
        return $this->mutate($session, function (array &$state) use ($remembered) {
            if (($state['current']['type'] ?? null) !== 'card') {
                return;
            }

            $item = array_shift($state['queue']);
            $this->requeue($state, $remembered ? ['t' => $item['t'], 's' => 'a'] : $item);
            $state['current'] = null;
            $state['n'] = ($state['n'] ?? 0) + 1;
            $state['flash'] = $remembered ? 'card' : 'again';
        });
    }

    /** Способ А: открыть следующую цифру. */
    public function hint(HistSession $session): HistSession
    {
        return $this->mutate($session, function (array &$state) {
            $current = $state['current'] ?? null;
            if (($current['type'] ?? null) !== 'a' || $current['result'] !== null) {
                return;
            }
            if ($current['hints'] < (int) config('dates.free_hints')) {
                $state['current']['hints']++;
                $state['flash'] = 'hint';
            }
        });
    }

    /**
     * Способ А: проверить год. $typed — цифры, введённые после открытых
     * подсказкой; $reveal — нажата «Показать ответ».
     */
    public function answer(HistSession $session, string $typed, bool $reveal): HistSession
    {
        return $this->mutate($session, function (array &$state, HistSession $session) use ($typed, $reveal) {
            $current = $state['current'] ?? null;
            if (($current['type'] ?? null) !== 'a' || $current['result'] !== null) {
                return;
            }

            $item = HistEvent::find($current['event_id']);
            $start = (int) $item->year_start;
            $end = $item->year_end === null ? null : (int) $item->year_end;
            $prefix = implode('', array_slice($this->years->cells($start, $end), 0, $current['hints']));
            $input = $prefix.$this->years->digits($typed);
            $correct = !$reveal && $this->years->matches($input, $start, $end);

            $state['current']['result'] = [
                'correct' => $correct,
                'revealed' => $reveal,
                'input' => $reveal ? '' : $input,
            ];
            $this->recordFirst($session, $state, $item->id, 'a', $correct);
            $this->react($state, $correct);
        });
    }

    /** Клик по варианту в квизе (b) или «лишнем факте» (c) — индекс в показанном списке. */
    public function choose(HistSession $session, int $choice): HistSession
    {
        return $this->mutate($session, function (array &$state, HistSession $session) use ($choice) {
            $current = $state['current'] ?? null;
            $type = $current['type'] ?? null;
            if (!in_array($type, ['b', 'c'], true) || $current['done'] || $current['reveal'] !== null) {
                return;
            }
            if (!isset($current['options'][$choice])) {
                return;
            }

            if ($type === 'c') {
                $correct = $current['options'][$choice] === $current['odd'];
                if ($correct) {
                    $state['current']['done'] = true;
                } else {
                    $state['current']['reveal'] = ['choice' => $choice];
                }
                $this->recordFirst($session, $state, $current['event_id'], 'b', $correct);
                $this->react($state, $correct);

                return;
            }

            $facts = $this->quizFacts($current);
            $correctId = $facts[$current['slot']]->id;

            if ($current['options'][$choice] === $correctId) {
                $this->advanceSlot($session, $state, $facts);
                $this->react($state, true);
                if (!$state['current']['done']) {
                    $state['flash'] = 'slot';
                }

                return;
            }

            $state['current']['reveal'] = ['choice' => $choice];
            $this->recordFirst($session, $state, $current['event_id'], 'b', false);
            $this->react($state, false);
        });
    }

    /** «Далее». */
    public function next(HistSession $session): HistSession
    {
        return $this->mutate($session, function (array &$state, HistSession $session) {
            $current = $state['current'] ?? null;
            $type = $current['type'] ?? null;

            if ($type === 'a' && $current['result'] !== null) {
                $this->finishStep($session, $state, $current['result']['correct']);
            } elseif (in_array($type, ['b', 'c'], true) && $current['reveal'] !== null) {
                $this->finishStep($session, $state, false);
            } elseif (in_array($type, ['b', 'c'], true) && $current['done']) {
                $this->finishStep($session, $state, true);
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Внутренняя механика
    |--------------------------------------------------------------------------
    */

    private function mutate(HistSession $session, \Closure $action): HistSession
    {
        return DB::transaction(function () use ($session, $action) {
            $locked = HistSession::whereKey($session->id)->lockForUpdate()->first();
            if ($locked->finished_at) {
                return $locked;
            }

            $state = $locked->state;
            $state['flash'] = null;
            $action($state, $locked);
            $this->settle($locked, $state);

            $locked->state = $state;
            $locked->save();

            return $locked;
        });
    }

    private function settle(HistSession $session, array &$state): void
    {
        while (($state['current'] ?? null) === null) {
            if ($state['queue'] === []) {
                $this->complete($session, $state);

                return;
            }

            $entry = $state['queue'][0];
            $id = (int) $entry['t'];
            $item = HistEvent::active()->find($id);
            if (!$item || $this->factsOf($id)->count() < 3) {
                // Элемент скрыли перезаливкой прямо во время сессии.
                $state['queue'] = array_values(array_filter($state['queue'], fn ($i) => (int) $i['t'] !== $id));
                if (isset($state['terms'])) {
                    $state['terms'] = array_values(array_diff($state['terms'], [$id]));
                }

                continue;
            }

            $state['current'] = match ($entry['s']) {
                'card' => ['type' => 'card', 'event_id' => $id],
                'a' => ['type' => 'a', 'event_id' => $id, 'hints' => 0, 'result' => null],
                'b' => $this->newQuizStep($item),
                'c' => $this->newOddStep($item),
            };
        }
    }

    private function advanceSlot(HistSession $session, array &$state, $facts): void
    {
        $slot = $state['current']['slot'] + 1;

        if ($slot >= $facts->count()) {
            $state['current']['done'] = true;
            $state['current']['final_options'] = $state['current']['options'];
            $state['current']['options'] = [];
            $this->recordFirst($session, $state, $state['current']['event_id'], 'b', true);

            return;
        }

        $state['current']['slot'] = $slot;
        $state['current']['options'] = $this->optionsFor(
            HistEvent::find($state['current']['event_id']),
            $facts[$slot],
            $state['current']['used'] ?? []
        );
        $state['current']['used'] = array_values(array_merge(
            $state['current']['used'] ?? [],
            array_diff($state['current']['options'], [(int) $facts[$slot]->id])
        ));
    }

    private function finishStep(HistSession $session, array &$state, bool $success): void
    {
        $item = array_shift($state['queue']);
        $state['current'] = null;
        $state['n'] = ($state['n'] ?? 0) + 1;
        $state['flash'] = $success ? 'card' : 'again';

        if (!$success) {
            $state['queue'][] = $item;

            return;
        }

        if ($session->kind === HistSession::KIND_LEARN && $item['s'] === 'a') {
            $this->requeue($state, ['t' => $item['t'], 's' => $state['quiz'][$item['t']] ?? 'b']);

            return;
        }

        $state['done'][] = (int) $item['t'];
    }

    private function react(array &$state, bool $correct): void
    {
        $state['combo'] = $correct ? ($state['combo'] ?? 0) + 1 : 0;
        $state['flash'] = $correct ? 'correct' : 'wrong';
    }

    private function requeue(array &$state, array $item): void
    {
        $at = min((int) config('dates.learn_gap'), count($state['queue']));
        array_splice($state['queue'], $at, 0, [$item]);
    }

    private function recordFirst(HistSession $session, array &$state, int $id, string $method, bool $correct): void
    {
        if ($session->kind === HistSession::KIND_LEARN) {
            $state['first'][$method][$id] ??= $correct;

            return;
        }

        if (array_key_exists($id, $state['first'])) {
            return;
        }
        $state['first'][$id] = $correct;

        if ($session->kind !== HistSession::KIND_REVIEW) {
            return;
        }

        $row = UserHistEvent::where('user_id', $session->user_id)->where('event_id', $id)->lockForUpdate()->first();
        if (!$row) {
            return;
        }

        $today = $this->today();
        $result = $this->schedule->afterReview((int) $row->step, $correct, $today);
        $row->step = $result['step'];
        $row->due_on = $result['due_on'];
        $row->last_reviewed_on = $today;
        $row->next_method = $this->schedule->nextMethod($row->next_method);
        $row->last_failed = !$correct;
        $row->save();
    }

    private function complete(HistSession $session, array &$state): void
    {
        $today = $this->today();
        $state['current'] = null;

        if ($session->kind === HistSession::KIND_LEARN) {
            foreach ($state['terms'] as $id) {
                $first = $this->schedule->afterLearning($today);
                UserHistEvent::firstOrCreate(
                    ['user_id' => $session->user_id, 'event_id' => $id],
                    [
                        'step' => $first['step'],
                        'due_on' => $first['due_on'],
                        'learned_on' => $today,
                        // Изучение кончается заданием на факты, поэтому
                        // первое повторение — ввод года.
                        'next_method' => UserHistEvent::METHOD_YEAR,
                        'last_failed' => ($state['first']['a'][$id] ?? true) === false
                            || ($state['first']['b'][$id] ?? true) === false,
                    ]
                );
            }

            $firsts = array_merge(array_values($state['first']['a']), array_values($state['first']['b']));
            $session->total = count($state['terms']) * 2;
        } else {
            $firsts = array_values($state['first']);
        }

        $session->correct_first = count(array_filter($firsts));
        $session->finished_at = now();

        if ($session->kind !== HistSession::KIND_FREE) {
            $corm = $session->day->toDateString() === $today
                ? $this->reward->corm((int) $session->correct_first, (int) $session->total)
                : 0;
            // Корм общий для всех тренажёров — то же начисление, что у терминов.
            $this->fish->awardTermSession(User::find($session->user_id), $corm);
            $session->corm_awarded = $corm;
        }
    }

    private function newQuizStep(HistEvent $item): array
    {
        $facts = $this->factsOf($item->id)
            ->shuffle()
            ->take((int) config('dates.quiz_features'))
            ->sortBy('position')
            ->values();

        $options = $this->optionsFor($item, $facts[0]);

        return [
            'type' => 'b', 'event_id' => (int) $item->id, 'slot' => 0,
            'slots' => $facts->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'options' => $options,
            'used' => array_values(array_diff($options, [(int) $facts[0]->id])),
            'reveal' => null, 'done' => false,
        ];
    }

    /** «Лишний факт»: три факта элемента и один чужой того же вида. */
    private function newOddStep(HistEvent $item): array
    {
        $own = $this->factsOf($item->id);
        $foreign = $this->picker->pick(
            ['term_id' => (int) $item->id, 'section' => (int) $item->era, 'group' => null, 'text' => ''],
            $this->relatedIds($item),
            $item->confusables()->pluck('hist_events.id')->map(fn ($id) => (int) $id)->all(),
            $this->picker->foreignPool($this->asPool($own), $this->factPool($item->kind)),
            1
        );

        if ($foreign === []) {
            return $this->newQuizStep($item);
        }

        $options = $own->shuffle()->take(3)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $options[] = (int) $foreign[0]['id'];
        shuffle($options);

        return [
            'type' => 'c', 'event_id' => (int) $item->id,
            'options' => $options,
            'odd' => (int) $foreign[0]['id'],
            'reveal' => null, 'done' => false,
        ];
    }

    /** Факты, выбранные для текущего квиза, в порядке показа. */
    public function quizFacts(array $current)
    {
        return HistFact::whereIn('id', $current['slots'])->orderBy('position')->get();
    }

    /**
     * Элементы, чьи факты не годятся в неверные варианты к этому:
     *  - связанные по линии parent (Смута и избрание Михаила Романова);
     *  - у правления — правления, пересекающиеся с ним по годам: факты
     *    регента и царя, соправителей верны для обоих.
     *
     * @return int[]
     */
    public function relatedIds(HistEvent $item): array
    {
        $parentOf = HistEvent::query()->where('kind', $item->kind)->pluck('parent_id', 'id')
            ->map(fn ($id) => $id === null ? null : (int) $id)->all();
        $related = $this->picker->relatedTermIds($parentOf, (int) $item->id);

        if ($item->isReign()) {
            $start = (int) $item->year_start;
            $end = $item->year_end === null ? PHP_INT_MAX : (int) $item->year_end;
            $overlapping = HistEvent::query()
                ->where('kind', HistEvent::KIND_REIGN)
                ->where('id', '!=', $item->id)
                ->where('year_start', '<=', $end)
                ->where(fn ($q) => $q->whereNull('year_end')->orWhere('year_end', '>=', $start))
                ->pluck('id')->map(fn ($id) => (int) $id)->all();
            $related = array_merge($related, $overlapping);
        }

        return array_values(array_unique($related));
    }

    /** Все факты элементов того же вида — в формате DistractorPicker (раздел — период). */
    private function factPool(string $kind): array
    {
        return $this->asPool(
            HistFact::query()
                ->join('hist_events', 'hist_events.id', '=', 'hist_facts.event_id')
                ->where('hist_events.is_active', true)
                ->where('hist_events.kind', $kind)
                ->where('hist_facts.is_active', true)
                ->get(['hist_facts.id', 'hist_facts.event_id', 'hist_facts.group', 'hist_facts.text', 'hist_events.era'])
        );
    }

    private function asPool($facts): array
    {
        return $facts->map(fn ($f) => [
            'id' => (int) $f->id,
            'term_id' => (int) $f->event_id,
            'section' => (int) ($f->era ?? 0),
            'group' => $f->group,
            'text' => $f->text,
        ])->all();
    }

    private function factsOf(int $id)
    {
        return HistFact::where('event_id', $id)->where('is_active', true)->orderBy('position')->get();
    }

    /** @return int[] id фактов в порядке показа */
    private function optionsFor(HistEvent $item, HistFact $correct, array $used = []): array
    {
        $distractors = $this->picker->pick(
            [
                'term_id' => (int) $item->id,
                'section' => (int) $item->era,
                'group' => $correct->group,
                'text' => $correct->text,
            ],
            $this->relatedIds($item),
            $item->confusables()->pluck('hist_events.id')->map(fn ($id) => (int) $id)->all(),
            $this->picker->foreignPool($this->asPool($this->factsOf($item->id)), $this->factPool($item->kind)),
            (int) config('dates.distractors'),
            $used,
            (int) config('dates.confusable_per_question')
        );

        $options = array_merge([(int) $correct->id], array_column($distractors, 'id'));
        shuffle($options);

        return $options;
    }
}
