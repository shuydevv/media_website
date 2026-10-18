<?php

namespace App\Service\Terms;

use App\Models\Term;
use App\Models\TermFeature;
use App\Models\TermSession;
use App\Models\User;
use App\Models\UserTerm;
use App\Service\FishFoodService;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Сессии тренажёра терминов: что доступно ученику сегодня, запуск сессии и
 * её пошаговое прохождение. Правила — docs/terms/Механика тренажёра терминов
 * задание 18.md; арифметика вынесена в TermSchedule / SessionReward /
 * AnswerMatcher / DistractorPicker, здесь только состояние и запись в БД.
 *
 * Состояние сессии лежит в term_sessions.state:
 *   queue    — шаги, которые ещё предстоит пройти, голова — текущий. Шаг —
 *              ['t' => id термина, 's' => 'card' | 'a' | 'b'];
 *   terms    — learn: порция целиком;
 *   done     — id терминов, пройденных до конца (для полосы прогресса);
 *   quiz     — каким заданием на признаки проверять термин: 'b' (квиз) или
 *              'c' (лишний признак). Внутри сессии они чередуются;
 *   combo    — сколько верных ответов подряд (только для показа, без корма);
 *   flash    — что произошло в последнем действии (correct / wrong / slot /
 *              card / again / hint) — по нему экран выбирает анимацию;
 *   n        — номер карточки: растёт при каждой смене задания, по нему
 *              экран понимает, что старую карточку пора «отбросить»;
 *   first    — результат ПЕРВОЙ попытки по термину (review/free: [id => bool],
 *              learn: ['a' => [...], 'b' => [...]]). Только он идёт в
 *              расписание и в корм: термин, вернувшийся на круг и отвеченный
 *              со второго раза, остаётся ошибкой;
 *   current  — текущий шаг (карточка / квадратики / квиз) с его деталями.
 *
 * Каждое действие ученика выполняется под блокировкой строки сессии
 * (mutate()), поэтому двойной клик не засчитывает ответ дважды.
 */
class TermTrainer
{
    private TermSchedule $schedule;

    private SessionReward $reward;

    private AnswerMatcher $matcher;

    private DistractorPicker $picker;

    public function __construct(private FishFoodService $fish)
    {
        $this->schedule = TermSchedule::fromConfig();
        $this->reward = SessionReward::fromConfig();
        $this->matcher = AnswerMatcher::fromConfig();
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
     * Состояние трёх строк блока «Сегодня» на главной тренажёра.
     *
     * review.status: available | in_progress | done | empty
     * learn.status:  available | in_progress | blocked_due | done_today | all_learned
     * free.status:   available | in_progress | blocked_review | empty
     */
    public function overview(User $user): array
    {
        $today = $this->today();

        $dueCount = $this->dueQuery($user, $today)->count();
        $learnedCount = $this->learnedQuery($user)->count();

        $reviewToday = $this->sessionOf($user, TermSession::KIND_REVIEW, $today);
        $learnToday = $this->sessionOf($user, TermSession::KIND_LEARN, $today);
        $freeOpen = TermSession::where('user_id', $user->id)
            ->where('kind', TermSession::KIND_FREE)
            ->whereNull('finished_at')
            ->latest('id')
            ->first();

        $limit = (int) config('terms.daily_review_limit');

        if ($reviewToday && !$reviewToday->finished_at) {
            $review = ['status' => 'in_progress', 'count' => $this->remainingCount($reviewToday)];
        } elseif ($reviewToday) {
            // Повторение дня одно: всё, что не влезло в потолок, ждёт завтра.
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
            // Иначе термины можно прогнать прямо перед повторением и получить
            // рост шагов без запоминания.
            $free = ['status' => 'blocked_review', 'count' => $learnedCount];
        } else {
            $free = ['status' => 'available', 'count' => $learnedCount];
        }

        return compact('review', 'learn', 'free', 'dueCount', 'learnedCount');
    }

    /**
     * Следующая порция новых терминов: уровни по порядку — базовый,
     * продвинутый, эксперт (Term::LEVELS); в одной порции не смешиваются. Внутри уровня порция
     * набирается по кругу из разных разделов кодификатора — по одному
     * термину из каждого, пока в разделах есть неизученные: вперемешку
     * термины запоминаются легче, а разделы продвигаются одновременно.
     * Внутри раздела — по порядку тем.
     */
    public function nextBatch(User $user)
    {
        $learned = UserTerm::where('user_id', $user->id)->pluck('term_id');
        $base = Term::active()->whereNotIn('id', $learned);

        // Самый лёгкий уровень, в котором ещё есть неизученные термины.
        $priority = (int) (clone $base)->min('priority');

        $bySection = $base->where('priority', $priority)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('section')
            ->sortKeys();

        $size = (int) config('terms.batch_size');
        $batch = collect();

        while ($batch->count() < $size && $bySection->isNotEmpty()) {
            foreach ($bySection as $section => $terms) {
                if ($batch->count() >= $size) {
                    break;
                }
                $batch->push($terms->shift());
                if ($terms->isEmpty()) {
                    $bySection->forget($section);
                }
            }
        }

        return $batch;
    }

    /**
     * Ближайший день, когда будет что повторять: дата и число терминов.
     * null — изученных терминов ещё нет.
     *
     * @return array{date: Carbon, count: int}|null
     */
    public function nextReview(User $user, string $today): ?array
    {
        $date = $this->learnedQuery($user)->whereDate('user_terms.due_on', '>', $today)->min('user_terms.due_on');
        if ($date === null) {
            return null;
        }

        $date = Carbon::parse($date)->startOfDay();

        return ['date' => $date, 'count' => $this->dueQuery($user, $date->toDateString())->count()];
    }

    /**
     * Сколько терминов будет ждать повторения завтра — для экрана итога.
     */
    public function dueTomorrow(User $user): int
    {
        return $this->dueQuery($user, now()->addDay()->toDateString())->count();
    }

    /**
     * Сколько терминов в сессии пройдено — одна цифра для полосы прогресса,
     * карточек тренажёра и рыбы на дашборде. Термин засчитывается на «Далее»
     * (state['done']), но успех в текущем задании ученик видит сразу —
     * поэтому он тоже считается. В изучении последний шаг термина — только
     * задание на признаки, в остальных сессиях — любое задание.
     */
    public function doneCount(TermSession $session): int
    {
        $state = $session->state;
        $done = count($state['done'] ?? []);
        $current = $state['current'] ?? null;
        $succeeded = match ($current['type'] ?? null) {
            'a' => $session->kind !== TermSession::KIND_LEARN && ($current['result']['correct'] ?? false),
            'b', 'c' => (bool) ($current['done'] ?? false),
            default => false,
        };

        return $done + ($succeeded ? 1 : 0);
    }

    /** Сколько терминов в сессии всего. */
    public function totalCount(TermSession $session): int
    {
        return $session->kind === TermSession::KIND_LEARN
            ? count($session->state['terms'] ?? [])
            : (int) $session->total;
    }

    /** Сколько терминов осталось пройти. Не очередь шагов: у термина их несколько, а ошибка возвращает его в очередь. */
    public function remainingCount(TermSession $session): int
    {
        return max(0, $this->totalCount($session) - $this->doneCount($session));
    }

    /** Сколько терминов ждут повторения со вчера и раньше — для рыбы на дашборде. */
    public function overdueCount(User $user): int
    {
        return $this->learnedQuery($user)->whereDate('user_terms.due_on', '<', $this->today())->count();
    }

    /**
     * Близко ли открытие следующего уровня: в текущем осталось не больше
     * двух дневных порций, а следующий уровень есть.
     */
    public function nearNextLevel(User $user): bool
    {
        $learned = UserTerm::where('user_id', $user->id)->pluck('term_id');
        $base = Term::active()->whereNotIn('id', $learned);
        $priority = (clone $base)->min('priority');
        if ($priority === null || !(clone $base)->where('priority', '>', $priority)->exists()) {
            return false;
        }

        return (clone $base)->where('priority', $priority)->count() <= 2 * (int) config('terms.batch_size');
    }

    private function dueQuery(User $user, string $date)
    {
        return $this->learnedQuery($user)->whereDate('user_terms.due_on', '<=', $date);
    }

    /** Прогресс ученика только по терминам, которые сейчас есть в базе. */
    private function learnedQuery(User $user)
    {
        return UserTerm::query()
            ->join('terms', 'terms.id', '=', 'user_terms.term_id')
            ->where('terms.is_active', true)
            ->where('user_terms.user_id', $user->id);
    }

    private function sessionOf(User $user, string $kind, string $day): ?TermSession
    {
        return TermSession::where('user_id', $user->id)
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
    public function start(User $user, string $kind): TermSession
    {
        return DB::transaction(function () use ($user, $kind) {
            // Блокировка строки ученика сериализует параллельные запуски
            // (двойной клик, две вкладки) — иначе создались бы две сессии.
            User::whereKey($user->id)->lockForUpdate()->first();

            $today = $this->today();
            $this->closeStale($user, $today);

            $status = $this->overview($user)[$kind]['status'] ?? null;

            if ($status === 'in_progress') {
                return $kind === TermSession::KIND_FREE
                    ? TermSession::where('user_id', $user->id)->where('kind', $kind)->whereNull('finished_at')->latest('id')->first()
                    : $this->sessionOf($user, $kind, $today);
            }
            if ($status !== 'available') {
                throw new DomainException('Эта сессия сейчас недоступна.');
            }

            [$state, $total] = match ($kind) {
                TermSession::KIND_LEARN => $this->newLearnState($user),
                TermSession::KIND_REVIEW => $this->newReviewState($user, $today),
                TermSession::KIND_FREE => $this->newFreeState($user),
            };

            return TermSession::create([
                'user_id' => $user->id,
                'kind' => $kind,
                'day' => $today,
                'state' => $state,
                'total' => $total,
                'correct_first' => 0,
            ]);
        });
    }

    /**
     * Изучение и повторение живут один календарный день: вчерашняя
     * незаконченная сессия закрывается без корма. Ответы повторения уже
     * записаны в расписание по ходу дела, а недоученная порция просто
     * начнётся заново (строки user_terms появляются только в конце изучения).
     */
    private function closeStale(User $user, string $today): void
    {
        TermSession::where('user_id', $user->id)
            ->whereIn('kind', [TermSession::KIND_LEARN, TermSession::KIND_REVIEW])
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
            ->orderBy('user_terms.due_on')
            ->orderBy('user_terms.step')
            ->orderBy('user_terms.id')
            ->limit((int) config('terms.daily_review_limit'))
            ->get(['user_terms.term_id', 'user_terms.next_method']);

        return [$this->queueState($rows), $rows->count()];
    }

    /**
     * Свободная тренировка: все изученные, сначала с самым низким шагом
     * (слабые места), внутри шага случайно.
     */
    private function newFreeState(User $user): array
    {
        // Свободная тренировка расписание не трогает, поэтому «уже было» —
        // по сегодняшним кругам: иначе каждый новый круг брал бы те же
        // самые слабые термины.
        $seenToday = TermSession::where('user_id', $user->id)
            ->where('kind', TermSession::KIND_FREE)
            ->whereDate('day', $this->today())
            ->get()
            ->flatMap(fn (TermSession $s) => array_keys($s->state['first'] ?? []))
            ->flip();

        $rows = $this->learnedQuery($user)
            ->get(['user_terms.term_id', 'user_terms.next_method', 'user_terms.step', 'user_terms.last_reviewed_on'])
            ->shuffle()
            ->sortBy(fn ($row) => [
                $seenToday->has($row->term_id) ? 1 : 0,
                (int) $row->step,
                (string) $row->last_reviewed_on,
            ])
            ->take((int) config('terms.free_session_limit'))
            ->values();

        return [$this->queueState($rows), $rows->count()];
    }

    private function queueState($rows): array
    {
        // В расписании у термина два хода: 'a' — квадратики, 'b' — задание на
        // признаки. Каким из двух заданий на признаки оно будет, решается
        // здесь, по очереди внутри сессии.
        $quiz = $this->alternateQuiz(
            $rows->filter(fn ($row) => $row->next_method !== UserTerm::METHOD_SQUARES)->pluck('term_id')->all()
        );

        return [
            'queue' => $rows->map(fn ($row) => [
                't' => (int) $row->term_id,
                's' => $row->next_method === UserTerm::METHOD_SQUARES ? 'a' : $quiz[(int) $row->term_id],
            ])->all(),
            'quiz' => $quiz,
            'first' => [],
            'done' => [],
            'current' => null,
        ];
    }

    /**
     * Квиз и «лишний признак» по очереди; с какого начать — случайно, иначе
     * первый термин порции всегда доставался бы квизу.
     *
     * @param int[] $termIds
     * @return array<int, string> id термина => 'b' | 'c'
     */
    private function alternateQuiz(array $termIds): array
    {
        $kinds = random_int(0, 1) ? ['b', 'c'] : ['c', 'b'];
        $quiz = [];
        foreach (array_values($termIds) as $i => $termId) {
            $quiz[(int) $termId] = $kinds[$i % 2];
        }

        return $quiz;
    }

    /*
    |--------------------------------------------------------------------------
    | Действия ученика
    |--------------------------------------------------------------------------
    */

    /**
     * Готовит текущий шаг, если он ещё не выбран (первый заход в сессию или
     * после перехода к следующему термину). Вызывается перед показом.
     */
    public function prepare(TermSession $session): TermSession
    {
        return $this->mutate($session, fn () => null);
    }

    /** Карточка знакомства: «Запомнил» / «Ещё не запомнил». */
    public function card(TermSession $session, bool $remembered): TermSession
    {
        return $this->mutate($session, function (array &$state) use ($remembered) {
            if (($state['current']['type'] ?? null) !== 'card') {
                return;
            }

            // «Запомнил» — термин уходит на первое задание, «Ещё не запомнил»
            // — карточка покажется снова. И то и другое не сразу, а через
            // пару шагов (см. requeue()).
            $item = array_shift($state['queue']);
            $this->requeue($state, $remembered ? ['t' => $item['t'], 's' => 'a'] : $item);
            $state['current'] = null;
            $state['n'] = ($state['n'] ?? 0) + 1;
            $state['flash'] = $remembered ? 'card' : 'again';
        });
    }

    /** Способ А: открыть следующую букву. */
    public function hint(TermSession $session): TermSession
    {
        return $this->mutate($session, function (array &$state) {
            $current = $state['current'] ?? null;
            if (($current['type'] ?? null) !== 'a' || $current['result'] !== null) {
                return;
            }
            if ($current['hints'] < (int) config('terms.free_hints')) {
                $state['current']['hints']++;
                $state['flash'] = 'hint';
            }
        });
    }

    /**
     * Способ А: проверить ответ. $typed — то, что ученик ввёл после уже
     * открытых подсказкой букв; $reveal — нажал «Показать ответ».
     */
    public function answer(TermSession $session, string $typed, bool $reveal): TermSession
    {
        return $this->mutate($session, function (array &$state, TermSession $session) use ($typed, $reveal) {
            $current = $state['current'] ?? null;
            if (($current['type'] ?? null) !== 'a' || $current['result'] !== null) {
                return;
            }

            $term = Term::find($current['term_id']);
            $prefix = implode('', array_slice($this->matcher->cells($term->title), 0, $current['hints']));
            $correct = !$reveal && $this->matcher->matches($prefix.$typed, $term->acceptedNames());

            $state['current']['result'] = [
                'correct' => $correct,
                'revealed' => $reveal,
                'input' => $reveal ? '' : $prefix.$typed,
            ];
            $this->recordFirst($session, $state, $term->id, 'a', $correct);
            $this->react($state, $correct);
        });
    }

    /**
     * Клик по варианту в заданиях на признаки (индекс в показанном списке):
     * квиз (b) — выбрать признак термина, «лишний признак» (c) — найти чужой.
     */
    public function choose(TermSession $session, int $choice): TermSession
    {
        return $this->mutate($session, function (array &$state, TermSession $session) use ($choice) {
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
                $this->recordFirst($session, $state, $current['term_id'], 'b', $correct);
                $this->react($state, $correct);

                return;
            }

            $features = $this->quizFeatures($current);
            $correctId = $features[$current['slot']]->id;

            if ($current['options'][$choice] === $correctId) {
                $this->advanceSlot($session, $state, $features);
                $this->react($state, true);
                // Слот заполнен, но квиз ещё идёт — другая анимация, чем на
                // законченном задании.
                if (!$state['current']['done']) {
                    $state['flash'] = 'slot';
                }

                return;
            }

            // Одна ошибка в любом признаке — ошибка по термину целиком:
            // остальные слоты уже не спрашиваем, после показа верного
            // варианта термин уходит в конец очереди (см. next()).
            $state['current']['reveal'] = ['choice' => $choice];
            $this->recordFirst($session, $state, $current['term_id'], 'b', false);
            $this->react($state, false);
        });
    }

    /**
     * «Далее»: после ответа в способе А, после неверного клика в способе Б
     * и после заполненного квиза.
     */
    public function next(TermSession $session): TermSession
    {
        return $this->mutate($session, function (array &$state, TermSession $session) {
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

    /**
     * Единая точка изменения сессии: блокирует строку, даёт $action поменять
     * состояние, затем доводит его до следующего показываемого шага (или
     * закрывает сессию, если очередь кончилась).
     */
    private function mutate(TermSession $session, \Closure $action): TermSession
    {
        return DB::transaction(function () use ($session, $action) {
            $locked = TermSession::whereKey($session->id)->lockForUpdate()->first();
            if ($locked->finished_at) {
                return $locked;
            }

            $state = $locked->state;
            // flash живёт один ответ: анимация проигрывается сразу после
            // действия и не повторяется при обновлении страницы.
            $state['flash'] = null;
            $action($state, $locked);
            $this->settle($locked, $state);

            $locked->state = $state;
            $locked->save();

            return $locked;
        });
    }

    /**
     * Если текущего шага нет — берёт следующий из очереди; пустая очередь
     * закрывает сессию.
     */
    private function settle(TermSession $session, array &$state): void
    {
        while (($state['current'] ?? null) === null) {
            if ($state['queue'] === []) {
                $this->complete($session, $state);

                return;
            }

            $item = $state['queue'][0];
            $termId = (int) $item['t'];
            $term = Term::active()->find($termId);
            if (!$term || $this->featuresOf($termId)->isEmpty()) {
                // Термин скрыли перезаливкой прямо во время сессии.
                $state['queue'] = array_values(array_filter($state['queue'], fn ($i) => (int) $i['t'] !== $termId));
                if (isset($state['terms'])) {
                    $state['terms'] = array_values(array_diff($state['terms'], [$termId]));
                }

                continue;
            }

            $state['current'] = match ($item['s']) {
                'card' => ['type' => 'card', 'term_id' => $termId],
                'a' => ['type' => 'a', 'term_id' => $termId, 'hints' => 0, 'result' => null],
                'b' => $this->newQuizStep($term),
                'c' => $this->newOddStep($term),
            };
        }
    }

    /** Способ Б: следующий слот или конец квиза по термину. */
    private function advanceSlot(TermSession $session, array &$state, $features): void
    {
        $slot = $state['current']['slot'] + 1;

        if ($slot >= $features->count()) {
            $state['current']['done'] = true;
            // Варианты последнего вопроса остаются на экране погасшими (см.
            // step-b) — иначе в карточке на пару секунд оставалось пустое
            // место. В options их больше нет: отвечать уже нельзя, и репорт
            // по пройденному квизу не отправляется.
            $state['current']['final_options'] = $state['current']['options'];
            $state['current']['options'] = [];
            $this->recordFirst($session, $state, $state['current']['term_id'], 'b', true);

            return;
        }

        $state['current']['slot'] = $slot;
        $state['current']['options'] = $this->optionsFor(
            Term::find($state['current']['term_id']),
            $features[$slot],
            $state['current']['used'] ?? []
        );
        // Дистракторы в одном квизе не повторяются — см. DistractorPicker.
        $state['current']['used'] = array_values(array_merge(
            $state['current']['used'] ?? [],
            array_diff($state['current']['options'], [(int) $features[$slot]->id])
        ));
    }

    /**
     * Текущий шаг пройден. Ошибка возвращает его в конец очереди. В изучении
     * успех в квадратиках ставит следом квиз по тому же термину; термин
     * считается пройденным после своего последнего шага.
     */
    private function finishStep(TermSession $session, array &$state, bool $success): void
    {
        $item = array_shift($state['queue']);
        $state['current'] = null;
        $state['n'] = ($state['n'] ?? 0) + 1;
        // Куда «отбросить» карточку: верно — в одну сторону, мимо — в другую.
        $state['flash'] = $success ? 'card' : 'again';

        if (!$success) {
            $state['queue'][] = $item;

            return;
        }

        if ($session->kind === TermSession::KIND_LEARN && $item['s'] === 'a') {
            $this->requeue($state, ['t' => $item['t'], 's' => $state['quiz'][$item['t']] ?? 'b']);

            return;
        }

        $state['done'][] = (int) $item['t'];
    }

    /**
     * Реакция на ответ: счётчик верных подряд и вспышка для анимации. Серия
     * только для показа — корма не даёт и между сессиями не переносится,
     * поэтому ошибка её просто обнуляет.
     */
    private function react(array &$state, bool $correct): void
    {
        $state['combo'] = $correct ? ($state['combo'] ?? 0) + 1 : 0;
        $state['flash'] = $correct ? 'correct' : 'wrong';
    }

    /**
     * Ставит шаг в очередь не сразу, а после нескольких других
     * (config('terms.learn_gap')). На этом держится чередование в изучении:
     * карточки не идут все подряд — после трёх «Запомнил» ученика уже
     * спрашивают про первый термин, а не показывают четвёртую карточку.
     */
    private function requeue(array &$state, array $item): void
    {
        $at = min((int) config('terms.learn_gap'), count($state['queue']));
        array_splice($state['queue'], $at, 0, [$item]);
    }

    /**
     * Записывает результат первой попытки по термину. В повторении он сразу
     * идёт в расписание — не в конце сессии, чтобы выход посередине не терял
     * уже данные ответы.
     */
    private function recordFirst(TermSession $session, array &$state, int $termId, string $method, bool $correct): void
    {
        if ($session->kind === TermSession::KIND_LEARN) {
            $state['first'][$method][$termId] ??= $correct;

            return;
        }

        if (array_key_exists($termId, $state['first'])) {
            return;
        }
        $state['first'][$termId] = $correct;

        if ($session->kind !== TermSession::KIND_REVIEW) {
            return;
        }

        $row = UserTerm::where('user_id', $session->user_id)->where('term_id', $termId)->lockForUpdate()->first();
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

    private function complete(TermSession $session, array &$state): void
    {
        $today = $this->today();
        $state['current'] = null;

        if ($session->kind === TermSession::KIND_LEARN) {
            foreach ($state['terms'] as $termId) {
                $first = $this->schedule->afterLearning($today);
                UserTerm::firstOrCreate(
                    ['user_id' => $session->user_id, 'term_id' => $termId],
                    [
                        'step' => $first['step'],
                        'due_on' => $first['due_on'],
                        'learned_on' => $today,
                        // Изучение кончается квизом, поэтому первое
                        // повторение — квадратики.
                        'next_method' => UserTerm::METHOD_SQUARES,
                        'last_failed' => ($state['first']['a'][$termId] ?? true) === false
                            || ($state['first']['b'][$termId] ?? true) === false,
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

        if ($session->kind !== TermSession::KIND_FREE) {
            // Сессия, перевалившая за полночь, корма не приносит.
            $corm = $session->day->toDateString() === $today
                ? $this->reward->corm((int) $session->correct_first, (int) $session->total)
                : 0;
            $this->fish->awardTermSession(User::find($session->user_id), $corm);
            $session->corm_awarded = $corm;
        }
    }

    /**
     * Новый квиз по термину. Спрашивается не больше
     * config('terms.quiz_features') признаков: у термина с пятью-шестью
     * берутся случайные четыре, каждый раз заново — за несколько повторений
     * ученик увидит все, а квиз не растягивается. Показываются в порядке базы.
     */
    private function newQuizStep(Term $term): array
    {
        $features = $this->featuresOf($term->id)
            ->shuffle()
            ->take((int) config('terms.quiz_features'))
            ->sortBy('position')
            ->values();

        $options = $this->optionsFor($term, $features[0]);

        return [
            'type' => 'b', 'term_id' => (int) $term->id, 'slot' => 0,
            'slots' => $features->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'options' => $options,
            // Уже показанные дистракторы — в следующих вопросах квиза не повторяются.
            'used' => array_values(array_diff($options, [(int) $features[0]->id])),
            'reveal' => null, 'done' => false,
        ];
    }

    /**
     * Задание «лишний признак»: до трёх признаков термина и один чужой,
     * найти нужно чужой. Чужой подбирается теми же правилами, что и
     * дистракторы квиза, и не должен быть верным для самого термина (см.
     * DistractorPicker::foreignPool()).
     */
    private function newOddStep(Term $term): array
    {
        $own = $this->featuresOf($term->id);
        $foreign = $this->picker->pick(
            ['term_id' => (int) $term->id, 'section' => (int) $term->section, 'group' => null, 'text' => ''],
            $this->picker->relatedTermIds($this->parentMap(), (int) $term->id),
            $term->confusables()->pluck('terms.id')->map(fn ($id) => (int) $id)->all(),
            $this->picker->foreignPool($this->asPool($own), $this->featurePool()),
            1
        );

        if ($foreign === []) {
            // Чужого признака не нашлось (на реальной базе не бывает) —
            // обычный квиз вместо сломанного задания.
            return $this->newQuizStep($term);
        }

        $options = $own->shuffle()->take(3)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $options[] = (int) $foreign[0]['id'];
        shuffle($options);

        return [
            'type' => 'c', 'term_id' => (int) $term->id,
            'options' => $options,
            'odd' => (int) $foreign[0]['id'],
            'reveal' => null, 'done' => false,
        ];
    }

    /**
     * Признаки, выбранные для текущего квиза, в порядке показа.
     */
    public function quizFeatures(array $current)
    {
        return TermFeature::whereIn('id', $current['slots'])->orderBy('position')->get();
    }

    /** Все признаки базы в виде, который понимает DistractorPicker. */
    private function featurePool(): array
    {
        return $this->asPool(
            TermFeature::query()
                ->join('terms', 'terms.id', '=', 'term_features.term_id')
                ->where('terms.is_active', true)
                ->where('term_features.is_active', true)
                ->get(['term_features.id', 'term_features.term_id', 'term_features.group', 'term_features.text', 'terms.section'])
        );
    }

    private function asPool($features): array
    {
        return $features->map(fn ($f) => [
            'id' => (int) $f->id,
            'term_id' => (int) $f->term_id,
            'section' => (int) ($f->section ?? 0),
            'group' => $f->group,
            'text' => $f->text,
        ])->all();
    }

    /** @return array<int, int|null> id термина => id родителя */
    private function parentMap(): array
    {
        return Term::query()->pluck('parent_id', 'id')->map(fn ($id) => $id === null ? null : (int) $id)->all();
    }

    private function featuresOf(int $termId)
    {
        return TermFeature::where('term_id', $termId)->where('is_active', true)->orderBy('position')->get();
    }

    /**
     * Варианты ответа для признака: он сам и дистракторы, перемешанные.
     * Сохраняются в состоянии — обновление страницы не меняет вопрос, а
     * репорт знает, какие дистракторы видел ученик.
     *
     * @return int[] id признаков в порядке показа
     */
    private function optionsFor(Term $term, TermFeature $correct, array $used = []): array
    {
        $distractors = $this->picker->pick(
            [
                'term_id' => (int) $term->id,
                'section' => (int) $term->section,
                'group' => $correct->group,
                'text' => $correct->text,
            ],
            $this->picker->relatedTermIds($this->parentMap(), (int) $term->id),
            $term->confusables()->pluck('terms.id')->map(fn ($id) => (int) $id)->all(),
            $this->picker->foreignPool($this->asPool($this->featuresOf($term->id)), $this->featurePool()),
            (int) config('terms.distractors'),
            $used,
            (int) config('terms.confusable_per_question')
        );

        $options = array_merge([(int) $correct->id], array_column($distractors, 'id'));
        shuffle($options);

        return $options;
    }
}
