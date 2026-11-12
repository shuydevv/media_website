<?php

namespace App\Service\Persons;

use App\Service\Demo\DemoMode;
use App\Models\Person;
use App\Models\PersonSession;
use App\Models\User;
use App\Models\UserPerson;
use App\Service\FishFoodService;
use App\Service\Terms\AnswerMatcher;
use App\Service\Terms\SessionReward;
use App\Service\Terms\TermSchedule;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Сессии тренажёра личностей. Механика — как у тренажёра дат
 * (App\Service\Dates\DateTrainer, там и у TermTrainer — подробные
 * комментарии к каждому правилу); здесь отличия:
 *
 *  - задания: «Чем известен?» (b: портрет и имя → ассоциация из четырёх),
 *    «Кто это?» (c: описание и ассоциация → личность из четырёх), ввод
 *    фамилии в клетки (a) и «Соответствие как в ЕГЭ» (d: 4 события и 6
 *    личностей);
 *  - изучение: карточка → b → c. Повторение чередует b и c, а с шага
 *    config('persons.type_from_step') вместо b приходит ввод фамилии — если
 *    у личности есть что вводить и фамилии нет в её же описании;
 *  - соответствие — финал повторения (если повторяли хотя бы четверых) и
 *    каждые пять личностей в свободной тренировке. На расписание и корм не
 *    влияет: это тренировка формата экзамена;
 *  - неверные варианты подбирает PersonPicker.
 *
 * Состояние сессии (person_sessions.state) устроено как у дат: id личности —
 * в 't' очереди и в 'person_id' текущего шага.
 */
class PersonTrainer
{
    private TermSchedule $schedule;

    private SessionReward $reward;

    private AnswerMatcher $matcher;

    private PersonPicker $picker;

    /** @var array[]|null все активные личности в формате PersonPicker */
    private ?array $pool = null;

    public function __construct(private FishFoodService $fish)
    {
        $this->schedule = new TermSchedule(config('persons.intervals'));
        $this->reward = new SessionReward(config('persons.corm'));
        $this->matcher = new AnswerMatcher((int) config('persons.typo_min_length'));
        $this->picker = new PersonPicker();
    }

    public function today(): string
    {
        return now()->toDateString();
    }

    public function matcher(): AnswerMatcher
    {
        return $this->matcher;
    }

    /*
    |--------------------------------------------------------------------------
    | Что доступно сегодня — как DateTrainer::overview()
    |--------------------------------------------------------------------------
    */

    public function overview(User $user): array
    {
        $today = $this->today();

        $dueCount = $this->dueQuery($user, $today)->count();
        $learnedCount = $this->learnedQuery($user)->count();

        $reviewToday = $this->sessionOf($user, PersonSession::KIND_REVIEW, $today);
        $learnToday = $this->sessionOf($user, PersonSession::KIND_LEARN, $today);
        $freeOpen = PersonSession::where('user_id', $user->id)
            ->where('kind', PersonSession::KIND_FREE)
            ->whereNull('finished_at')
            ->latest('id')
            ->first();

        $limit = (int) config('persons.daily_review_limit');

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

    /** Следующая порция: самый лёгкий уровень с неизученными, по sort_order (поле order файла). */
    public function nextBatch(User $user)
    {
        $learned = UserPerson::where('user_id', $user->id)->pluck('person_id');
        // Демо-ученик изучает только лёгкий набор (config/demo.php); неверные
        // варианты и соответствия — по-прежнему из всей базы, иначе рядом
        // с Лениным оказались бы Пётр I и Кутузов.
        $base = DemoMode::restrict(Person::active()->whereNotIn('id', $learned), 'persons', 'slug');

        $level = (int) (clone $base)->min('level');

        return $base->where('level', $level)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit((int) config('persons.batch_size'))
            ->get();
    }

    /** @return array{date: Carbon, count: int}|null */
    public function nextReview(User $user, string $today): ?array
    {
        $date = $this->learnedQuery($user)->whereDate('user_persons.due_on', '>', $today)->min('user_persons.due_on');
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

    /** Сколько личностей сессии уже пройдено (соответствие не считается). */
    public function doneCount(PersonSession $session): int
    {
        $state = $session->state;
        $done = count($state['done'] ?? []);
        $current = $state['current'] ?? null;
        $isLearn = $session->kind === PersonSession::KIND_LEARN;
        $succeeded = match ($current['type'] ?? null) {
            'a' => ($current['result']['correct'] ?? false),
            'b' => !$isLearn && ($current['done'] ?? false),
            'c' => (bool) ($current['done'] ?? false),
            default => false,
        };

        return $done + ($succeeded ? 1 : 0);
    }

    public function totalCount(PersonSession $session): int
    {
        return $session->kind === PersonSession::KIND_LEARN
            ? count($session->state['terms'] ?? [])
            : (int) $session->total;
    }

    public function remainingCount(PersonSession $session): int
    {
        return max(0, $this->totalCount($session) - $this->doneCount($session));
    }

    public function overdueCount(User $user): int
    {
        return $this->learnedQuery($user)->whereDate('user_persons.due_on', '<', $this->today())->count();
    }

    public function nearNextLevel(User $user): bool
    {
        $learned = UserPerson::where('user_id', $user->id)->pluck('person_id');
        // Демо-ученик изучает только лёгкий набор (config/demo.php); неверные
        // варианты и соответствия — по-прежнему из всей базы, иначе рядом
        // с Лениным оказались бы Пётр I и Кутузов.
        $base = DemoMode::restrict(Person::active()->whereNotIn('id', $learned), 'persons', 'slug');
        $level = (clone $base)->min('level');
        if ($level === null || !(clone $base)->where('level', '>', $level)->exists()) {
            return false;
        }

        return (clone $base)->where('level', $level)->count() <= 2 * (int) config('persons.batch_size');
    }

    private function dueQuery(User $user, string $date)
    {
        return $this->learnedQuery($user)->whereDate('user_persons.due_on', '<=', $date);
    }

    private function learnedQuery(User $user)
    {
        return UserPerson::query()
            ->join('persons', 'persons.id', '=', 'user_persons.person_id')
            ->where('persons.is_active', true)
            ->where('user_persons.user_id', $user->id);
    }

    private function sessionOf(User $user, string $kind, string $day): ?PersonSession
    {
        return PersonSession::where('user_id', $user->id)
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

    /** @throws DomainException если сессия этого вида сейчас недоступна */
    public function start(User $user, string $kind): PersonSession
    {
        return DB::transaction(function () use ($user, $kind) {
            User::whereKey($user->id)->lockForUpdate()->first();

            $today = $this->today();
            $this->closeStale($user, $today);

            $status = $this->overview($user)[$kind]['status'] ?? null;

            if ($status === 'in_progress') {
                return $kind === PersonSession::KIND_FREE
                    ? PersonSession::where('user_id', $user->id)->where('kind', $kind)->whereNull('finished_at')->latest('id')->first()
                    : $this->sessionOf($user, $kind, $today);
            }
            if ($status !== 'available') {
                throw new DomainException('Эта сессия сейчас недоступна.');
            }

            [$state, $total] = match ($kind) {
                PersonSession::KIND_LEARN => $this->newLearnState($user),
                PersonSession::KIND_REVIEW => $this->newReviewState($user, $today),
                PersonSession::KIND_FREE => $this->newFreeState($user),
            };

            return PersonSession::create([
                'user_id' => $user->id,
                'kind' => $kind,
                'day' => $today,
                'state' => $state,
                'total' => $total,
                'correct_first' => 0,
            ]);
        });
    }

    private function closeStale(User $user, string $today): void
    {
        PersonSession::where('user_id', $user->id)
            ->whereIn('kind', [PersonSession::KIND_LEARN, PersonSession::KIND_REVIEW])
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
            'first' => ['b' => [], 'c' => []],
            'done' => [],
            'match' => [],
            'current' => null,
        ], count($ids) * 2];
    }

    private function newReviewState(User $user, string $today): array
    {
        $rows = $this->dueQuery($user, $today)
            ->orderBy('user_persons.due_on')
            ->orderBy('user_persons.step')
            ->orderBy('user_persons.id')
            ->limit((int) config('persons.daily_review_limit'))
            ->get(['user_persons.person_id', 'user_persons.next_method', 'user_persons.step']);

        $queue = $rows->map(fn ($row) => ['t' => (int) $row->person_id, 's' => $this->methodFor($row)])->all();
        $ids = array_column($queue, 't');
        if (count($ids) >= (int) config('persons.match_rows')) {
            $queue[] = ['t' => 0, 's' => 'd', 'ids' => $ids];
        }

        return [$this->queueState($queue), $rows->count()];
    }

    private function newFreeState(User $user): array
    {
        $seenToday = PersonSession::where('user_id', $user->id)
            ->where('kind', PersonSession::KIND_FREE)
            ->whereDate('day', $this->today())
            ->get()
            ->flatMap(fn (PersonSession $s) => array_keys($s->state['first'] ?? []))
            ->flip();

        $rows = $this->learnedQuery($user)
            ->get(['user_persons.person_id', 'user_persons.next_method', 'user_persons.step', 'user_persons.last_reviewed_on'])
            ->shuffle()
            ->sortBy(fn ($row) => [
                $seenToday->has($row->person_id) ? 1 : 0,
                (int) $row->step,
                (string) $row->last_reviewed_on,
            ])
            ->take((int) config('persons.free_session_limit'))
            ->values();

        $every = max(1, (int) config('persons.match_every'));
        $minRows = (int) config('persons.match_rows');
        $queue = [];
        $ids = [];
        foreach ($rows as $i => $row) {
            $queue[] = ['t' => (int) $row->person_id, 's' => $this->methodFor($row)];
            $ids[] = (int) $row->person_id;
            $isChunkEnd = ($i + 1) % $every === 0;
            $isLast = $i === $rows->count() - 1;
            if (($isChunkEnd || ($isLast && ($i + 1) % $every >= 2)) && count($ids) >= $minRows) {
                $queue[] = ['t' => 0, 's' => 'd', 'ids' => array_slice($ids, -$every)];
            }
        }

        return [$this->queueState($queue), $rows->count()];
    }

    /** Каким заданием спросить в повторении: ввод фамилии — только с нужного шага и если есть что вводить. */
    private function methodFor($row): string
    {
        $method = (string) $row->next_method;
        if ($method === UserPerson::METHOD_TYPE) {
            $person = Person::find($row->person_id);
            if ((int) $row->step < (int) config('persons.type_from_step') || !$person || !$this->canType($person)) {
                return UserPerson::METHOD_KNOWN;
            }
        }

        return in_array($method, ['a', 'b', 'c'], true) ? $method : UserPerson::METHOD_KNOWN;
    }

    private function queueState(array $queue): array
    {
        return [
            'queue' => $queue,
            'first' => [],
            'done' => [],
            'match' => [],
            'current' => null,
        ];
    }

    /**
     * Можно ли спросить фамилию: она есть, и её нет в описании или
     * ассоциации («Стахановское движение» у Стаханова — ответ на экране).
     */
    public function canType(Person $person): bool
    {
        if ($person->answer === null || $person->answer === '') {
            return false;
        }

        $answer = $this->matcher->normalize($person->answer);
        $stem = mb_substr($answer, 0, max(4, mb_strlen($answer) - 2));
        $clue = $this->matcher->normalize($person->description.' '.$person->association);

        return !str_contains($clue, $stem);
    }

    /*
    |--------------------------------------------------------------------------
    | Действия ученика
    |--------------------------------------------------------------------------
    */

    public function prepare(PersonSession $session): PersonSession
    {
        return $this->mutate($session, fn () => null);
    }

    /** Карточка знакомства: «Запомнил» / «Ещё не запомнил». */
    public function card(PersonSession $session, bool $remembered): PersonSession
    {
        return $this->mutate($session, function (array &$state) use ($remembered) {
            if (($state['current']['type'] ?? null) !== 'card') {
                return;
            }

            $item = array_shift($state['queue']);
            $this->requeue($state, $remembered ? ['t' => $item['t'], 's' => 'b'] : $item);
            $state['current'] = null;
            $state['n'] = ($state['n'] ?? 0) + 1;
            $state['flash'] = $remembered ? 'card' : 'again';
        });
    }

    /** Ввод фамилии — открыть букву; «Кто это?» — показать связи. */
    public function hint(PersonSession $session): PersonSession
    {
        return $this->mutate($session, function (array &$state) {
            $current = $state['current'] ?? null;
            $type = $current['type'] ?? null;

            if ($type === 'a' && $current['result'] === null && $current['hints'] < (int) config('persons.free_hints')) {
                $state['current']['hints']++;
                $state['flash'] = 'hint';
            }
            if ($type === 'c' && !$current['done'] && $current['reveal'] === null) {
                $state['current']['hint'] = true;
                $state['flash'] = 'hint';
            }
        });
    }

    /** Ввод фамилии: $typed — буквы после открытых подсказкой; $reveal — «Показать ответ». */
    public function answer(PersonSession $session, string $typed, bool $reveal): PersonSession
    {
        return $this->mutate($session, function (array &$state, PersonSession $session) use ($typed, $reveal) {
            $current = $state['current'] ?? null;
            if (($current['type'] ?? null) !== 'a' || $current['result'] !== null) {
                return;
            }

            $person = Person::find($current['person_id']);
            $prefix = implode('', array_slice($this->matcher->cells($person->answer), 0, $current['hints']));
            $correct = !$reveal && $this->matcher->matches($prefix.$typed, [$person->answer]);

            $state['current']['result'] = [
                'correct' => $correct,
                'revealed' => $reveal,
                'input' => $reveal ? '' : $prefix.$typed,
            ];
            $this->recordFirst($session, $state, $person->id, 'a', $correct);
            $this->react($state, $correct);
        });
    }

    /** «Чем известен?» и «Кто это?»: индекс варианта в показанном списке. */
    public function choose(PersonSession $session, int $choice): PersonSession
    {
        return $this->mutate($session, function (array &$state, PersonSession $session) use ($choice) {
            $current = $state['current'] ?? null;
            $type = $current['type'] ?? null;
            if (!in_array($type, ['b', 'c'], true) || $current['done'] || $current['reveal'] !== null) {
                return;
            }
            if (!isset($current['options'][$choice])) {
                return;
            }

            $correct = (int) $current['options'][$choice] === (int) $current['person_id'];
            if ($correct) {
                $state['current']['done'] = true;
            } else {
                $state['current']['reveal'] = ['choice' => $choice];
            }
            $this->recordFirst($session, $state, (int) $current['person_id'], $type, $correct);
            $this->react($state, $correct);
        });
    }

    /**
     * Соответствие: $answer — цифры по буквам (как в бланке ЕГЭ), «0» —
     * буква пропущена. Засчитывается каждая буква отдельно.
     */
    public function match(PersonSession $session, string $answer): PersonSession
    {
        return $this->mutate($session, function (array &$state) use ($answer) {
            $current = $state['current'] ?? null;
            if (($current['type'] ?? null) !== 'd' || $current['result'] !== null) {
                return;
            }

            $digits = str_split(substr(preg_replace('/\D/', '', $answer), 0, count($current['rows'])));
            $ok = [];
            foreach ($current['rows'] as $i => $personId) {
                $digit = (int) ($digits[$i] ?? 0);
                $ok[] = $digit > 0 && (int) ($current['options'][$digit - 1] ?? 0) === (int) $personId;
            }

            $state['current']['result'] = ['answer' => implode('', array_map(fn ($i) => $digits[$i] ?? '0', array_keys($current['rows']))), 'ok' => $ok];
            $state['match'][] = ['ok' => count(array_filter($ok)), 'of' => count($ok)];
            $this->react($state, !in_array(false, $ok, true));
        });
    }

    /** «Далее». */
    public function next(PersonSession $session): PersonSession
    {
        return $this->mutate($session, function (array &$state, PersonSession $session) {
            $current = $state['current'] ?? null;
            $type = $current['type'] ?? null;

            if ($type === 'a' && $current['result'] !== null) {
                $this->finishStep($session, $state, $current['result']['correct']);
            } elseif (in_array($type, ['b', 'c'], true) && $current['reveal'] !== null) {
                $this->finishStep($session, $state, false);
            } elseif (in_array($type, ['b', 'c'], true) && $current['done']) {
                $this->finishStep($session, $state, true);
            } elseif ($type === 'd' && $current['result'] !== null) {
                array_shift($state['queue']);
                $state['current'] = null;
                $state['n'] = ($state['n'] ?? 0) + 1;
                $state['flash'] = 'card';
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Внутренняя механика
    |--------------------------------------------------------------------------
    */

    private function mutate(PersonSession $session, \Closure $action): PersonSession
    {
        return DB::transaction(function () use ($session, $action) {
            $locked = PersonSession::whereKey($session->id)->lockForUpdate()->first();
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

    private function settle(PersonSession $session, array &$state): void
    {
        while (($state['current'] ?? null) === null) {
            if ($state['queue'] === []) {
                $this->complete($session, $state);

                return;
            }

            $entry = $state['queue'][0];

            if ($entry['s'] === 'd') {
                $learned = UserPerson::where('user_id', $session->user_id)->pluck('person_id')->map(fn ($id) => (int) $id)->all();
                $step = $this->newMatchStep($entry['ids'] ?? [], $learned);
                if ($step === null) {
                    array_shift($state['queue']);
                    continue;
                }
                $state['current'] = $step;
                continue;
            }

            $id = (int) $entry['t'];
            $person = Person::active()->find($id);
            if (!$person) {
                // Личность скрыли перезаливкой прямо во время сессии.
                $state['queue'] = array_values(array_filter($state['queue'], fn ($i) => (int) $i['t'] !== $id));
                if (isset($state['terms'])) {
                    $state['terms'] = array_values(array_diff($state['terms'], [$id]));
                }
                continue;
            }

            $kind = $entry['s'] === 'a' && !$this->canType($person) ? 'b' : $entry['s'];
            $state['current'] = match ($kind) {
                'card' => ['type' => 'card', 'person_id' => $id],
                'a' => ['type' => 'a', 'person_id' => $id, 'hints' => 0, 'result' => null],
                'b', 'c' => $this->newChoiceStep($person, $kind),
            };
        }
    }

    private function newChoiceStep(Person $person, string $type): array
    {
        $target = $this->asPick($person);
        $distractors = $this->picker->pick($target, $this->pool(), (int) config('persons.distractors'));
        $options = array_merge([(int) $person->id], array_map(fn ($p) => (int) $p['id'], $distractors));
        shuffle($options);

        return [
            'type' => $type, 'person_id' => (int) $person->id,
            'options' => $options,
            'hint' => false,
            'reveal' => null, 'done' => false,
        ];
    }

    /** Строки — из повторённых сейчас; если среди них спорные пары, добираем из изученных раньше. */
    private function newMatchStep(array $ids, array $learned): ?array
    {
        $set = $this->picker->matching(
            $ids,
            $this->pool(),
            (int) config('persons.match_rows'),
            (int) config('persons.match_extra'),
            $learned
        );

        return $set === null ? null : [
            'type' => 'd', 'person_id' => 0,
            'rows' => $set['rows'],
            'options' => $set['options'],
            'result' => null,
        ];
    }

    private function finishStep(PersonSession $session, array &$state, bool $success): void
    {
        $item = array_shift($state['queue']);
        $state['current'] = null;
        $state['n'] = ($state['n'] ?? 0) + 1;
        $state['flash'] = $success ? 'card' : 'again';

        if (!$success) {
            $state['queue'][] = $item;

            return;
        }

        if ($session->kind === PersonSession::KIND_LEARN && $item['s'] === 'b') {
            $this->requeue($state, ['t' => $item['t'], 's' => 'c']);

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
        $at = min((int) config('persons.learn_gap'), count($state['queue']));
        array_splice($state['queue'], $at, 0, [$item]);
    }

    private function recordFirst(PersonSession $session, array &$state, int $id, string $method, bool $correct): void
    {
        if ($session->kind === PersonSession::KIND_LEARN) {
            $state['first'][$method][$id] ??= $correct;

            return;
        }

        if (array_key_exists($id, $state['first'])) {
            return;
        }
        $state['first'][$id] = $correct;

        if ($session->kind !== PersonSession::KIND_REVIEW) {
            return;
        }

        $row = UserPerson::where('user_id', $session->user_id)->where('person_id', $id)->lockForUpdate()->first();
        if (!$row) {
            return;
        }

        $today = $this->today();
        $result = $this->schedule->afterReview((int) $row->step, $correct, $today);
        $row->step = $result['step'];
        $row->due_on = $result['due_on'];
        $row->last_reviewed_on = $today;
        $row->next_method = $this->nextMethod($method, (int) $result['step']);
        $row->last_failed = !$correct;
        $row->save();
    }

    /**
     * Чередование заданий: «Чем известен?» → «Кто это?» → снова «Чем
     * известен?» или, с шага type_from_step, ввод фамилии. Можно ли ввести
     * фамилию именно у этой личности, проверяется при сборке очереди.
     */
    public function nextMethod(string $method, int $step): string
    {
        return match ($method) {
            UserPerson::METHOD_KNOWN => UserPerson::METHOD_WHO,
            UserPerson::METHOD_WHO => $step >= (int) config('persons.type_from_step') ? UserPerson::METHOD_TYPE : UserPerson::METHOD_KNOWN,
            default => UserPerson::METHOD_KNOWN,
        };
    }

    private function complete(PersonSession $session, array &$state): void
    {
        $today = $this->today();
        $state['current'] = null;

        if ($session->kind === PersonSession::KIND_LEARN) {
            foreach ($state['terms'] as $id) {
                $first = $this->schedule->afterLearning($today);
                UserPerson::firstOrCreate(
                    ['user_id' => $session->user_id, 'person_id' => $id],
                    [
                        'step' => $first['step'],
                        'due_on' => $first['due_on'],
                        'learned_on' => $today,
                        // Изучение кончается заданием «Кто это?», первое
                        // повторение — «Чем известен?», как в ЕГЭ.
                        'next_method' => UserPerson::METHOD_KNOWN,
                        'last_failed' => ($state['first']['b'][$id] ?? true) === false
                            || ($state['first']['c'][$id] ?? true) === false,
                    ]
                );
            }

            $firsts = array_merge(array_values($state['first']['b']), array_values($state['first']['c']));
            $session->total = count($state['terms']) * 2;
        } else {
            $firsts = array_values($state['first']);
        }

        $session->correct_first = count(array_filter($firsts));
        $session->finished_at = now();

        if ($session->kind !== PersonSession::KIND_FREE) {
            $corm = $session->day->toDateString() === $today
                ? $this->reward->corm((int) $session->correct_first, (int) $session->total)
                : 0;
            // Корм общий для всех тренажёров — то же начисление, что у терминов.
            $this->fish->awardTermSession(User::find($session->user_id), $corm);
            $session->corm_awarded = $corm;
        }
    }

    /** @return array[] все активные личности в формате PersonPicker */
    private function pool(): array
    {
        if ($this->pool === null) {
            $slugToId = Person::active()->pluck('id', 'slug')->map(fn ($id) => (int) $id)->all();
            $this->pool = Person::active()->get()->map(fn (Person $p) => $this->asPick($p, $slugToId))->all();
        }

        return $this->pool;
    }

    private function asPick(Person $person, ?array $slugToId = null): array
    {
        $slugToId ??= Person::whereIn('slug', $person->related_slugs ?? [])->pluck('id', 'slug')->map(fn ($id) => (int) $id)->all();

        return [
            'id' => (int) $person->id,
            'name' => $person->name,
            'era' => (int) $person->era,
            'role' => $person->role,
            'association' => $person->association,
            'description' => $person->description,
            'related' => array_values(array_filter(array_map(fn ($slug) => $slugToId[$slug] ?? null, $person->related_slugs ?? []))),
        ];
    }
}
