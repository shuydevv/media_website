<?php

namespace App\Service\Bank;

use App\Models\Task;
use App\Models\TaskAttempt;
use App\Models\TaskRound;
use App\Models\User;
use App\Models\UserTask;
use App\Service\FishFoodService;
use App\Service\Homework\AutoGrader;
use App\Service\Statements\StatementSchedule;
use App\Service\Terms\SessionReward;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Станция «Задания», первая часть: раунды, ответы, повтор ошибок, корм.
 * Механика — docs/bank/Задания — механика.md. Проверка — тот же
 * AutoGrader, что в домашках; когда повторять — та же лесенка, что в
 * «Верных суждениях» (StatementSchedule с интервалами config task_trainer).
 */
class TaskTrainer
{
    private StatementSchedule $schedule;

    private SessionReward $reward;

    /** @var callable(array): array перемешивание — подменяется в тестах */
    private $shuffle;

    public function __construct(private FishFoodService $fish, private AutoGrader $grader, ?callable $shuffle = null)
    {
        $this->schedule = new StatementSchedule(config('task_trainer.intervals'));
        $this->reward = new SessionReward(config('task_trainer.corm'));
        $this->shuffle = $shuffle ?? function (array $items): array {
            shuffle($items);

            return $items;
        };
    }

    public function today(): string
    {
        return now()->toDateString();
    }

    /** Номера первой части, по которым есть задания. */
    public static function part1Numbers(): array
    {
        return range(1, 16);
    }

    public static function part2Numbers(): array
    {
        return range(17, 25);
    }

    /*
    |--------------------------------------------------------------------------
    | Обзор
    |--------------------------------------------------------------------------
    */

    /**
     * @return array{numbers: array, due: int, unfinished: ?TaskRound, corm: array}
     *   numbers — [n => {title, total, solved, due}]
     */
    public function overview(User $user): array
    {
        $today = $this->today();
        $totals = Task::forTrainer()->selectRaw('number, count(*) as n')->groupBy('number')->pluck('n', 'number');
        $progress = UserTask::query()
            ->join('tasks', 'tasks.id', '=', 'user_tasks.task_id')
            ->where('user_tasks.user_id', $user->id)
            ->where('tasks.status', 'active')
            ->get(['tasks.number', 'user_tasks.due_on']);

        $numbers = [];
        foreach (range(1, 25) as $n) {
            $rows = $progress->where('number', (string) $n);
            $numbers[$n] = [
                'n' => $n,
                'title' => config('task_trainer.numbers.'.$n),
                'total' => (int) ($totals[(string) $n] ?? 0),
                'solved' => $rows->count(),
                'due' => $rows->filter(fn ($r) => $r->due_on && $r->due_on->toDateString() <= $today)->count(),
            ];
        }

        return [
            'numbers' => $numbers,
            'due' => array_sum(array_column($numbers, 'due')),
            'unfinished' => $this->unfinished($user),
            'corm' => $this->cormToday((int) $user->id),
        ];
    }

    public function unfinished(User $user): ?TaskRound
    {
        return TaskRound::query()
            ->where('user_id', $user->id)
            ->where('status', TaskRound::IN_PROGRESS)
            ->latest('id')
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | Старт
    |--------------------------------------------------------------------------
    */

    /**
     * Новый раунд. Пустые блоки — все блоки. null — под выбор нет заданий.
     * Порядок отбора: ошибки на повтор (самые давние первыми) → новые →
     * уже решённые (самые давние) — если новых не хватило.
     *
     * @param  int[]  $numbers
     * @param  string[]  $blocks
     */
    public function start(User $user, array $numbers, array $blocks = []): ?TaskRound
    {
        $numbers = array_values(array_intersect(self::part1Numbers(), array_map('intval', $numbers)));
        $blocks = array_values(array_intersect(array_keys(config('statements.blocks')), $blocks));
        if (! $numbers) {
            return null;
        }

        return DB::transaction(function () use ($user, $numbers, $blocks) {
            User::whereKey($user->id)->lockForUpdate()->first();
            $today = $this->today();
            $size = (int) config('task_trainer.round_size');

            $base = fn () => Task::forTrainer()
                ->whereIn('tasks.number', array_map('strval', $numbers))
                ->when($blocks, fn ($q) => $q->whereIn('tasks.block', $blocks));
            $mine = fn () => $base()->join('user_tasks', function ($join) use ($user) {
                $join->on('user_tasks.task_id', '=', 'tasks.id')->where('user_tasks.user_id', $user->id);
            });
            $pluck = fn ($q) => $q->get(['tasks.id', 'tasks.theme'])
                ->map(fn ($t) => ['id' => (int) $t->id, 'theme' => $t->theme])->all();

            $due = $pluck($mine()->whereDate('user_tasks.due_on', '<=', $today)->orderBy('user_tasks.due_on'));
            $unseen = $pluck($base()
                ->whereNotExists(function ($q) use ($user) {
                    $q->selectRaw('1')->from('user_tasks')
                        ->whereColumn('user_tasks.task_id', 'tasks.id')
                        ->where('user_tasks.user_id', $user->id);
                })
                ->inRandomOrder()
                ->limit($size * 4));
            $seen = $pluck($mine()
                ->where(fn ($q) => $q->whereNull('user_tasks.due_on')->orWhereDate('user_tasks.due_on', '>', $today))
                ->orderBy('user_tasks.last_answered_at')
                ->limit($size * 4));

            $ids = $this->pick($due, $unseen, $seen, $size);
            if (! $ids) {
                return null;
            }

            $this->abandonOpen($user);

            return TaskRound::create([
                'user_id' => $user->id,
                'kind' => TaskRound::KIND_REGULAR,
                'numbers' => $numbers,
                'blocks' => $blocks,
                'items' => $ids,
                'results' => [],
                'total' => count($ids),
                'day' => $today,
            ]);
        });
    }

    /**
     * Чистый отбор: повтор → новые (перемешанные) → решённые. Задания одной
     * темы подряд не идут, если есть чем разделить.
     *
     * @return int[]
     */
    public function pick(array $due, array $unseen, array $seen, int $size): array
    {
        $chosen = array_slice(array_values($due), 0, $size);
        foreach ([($this->shuffle)(array_values($unseen)), array_values($seen)] as $pool) {
            foreach ($pool as $item) {
                if (count($chosen) >= $size) {
                    break 2;
                }
                $chosen[] = $item;
            }
        }

        return array_column($this->spread(($this->shuffle)($chosen)), 'id');
    }

    private function spread(array $items): array
    {
        $groups = [];
        foreach ($items as $i => $item) {
            $groups[$item['theme'] === null ? 'i'.$i : 't'.$item['theme']][] = $item;
        }
        $result = [];
        $last = null;
        while ($groups) {
            $best = null;
            foreach ($groups as $key => $group) {
                if ($key === $last && count($groups) > 1) {
                    continue;
                }
                if ($best === null || count($group) > count($groups[$best])) {
                    $best = $key;
                }
            }
            $result[] = array_shift($groups[$best]);
            if (! $groups[$best]) {
                unset($groups[$best]);
            }
            $last = $best;
        }

        return $result;
    }

    /** «Повторить ошибки»: задания раунда, где набран не максимум. Без корма и расписания. */
    public function startMistakes(User $user, TaskRound $parent): ?TaskRound
    {
        if ((int) $parent->user_id !== (int) $user->id || ! $parent->isFinished()) {
            return null;
        }
        $ids = [];
        foreach ($parent->items as $i => $id) {
            $res = $parent->results[$i] ?? null;
            if (! $res || (int) $res['score'] < (int) $res['max']) {
                $ids[] = (int) $id;
            }
        }
        if (! $ids) {
            return null;
        }
        shuffle($ids);

        return DB::transaction(function () use ($user, $parent, $ids) {
            User::whereKey($user->id)->lockForUpdate()->first();
            $this->abandonOpen($user);

            return TaskRound::create([
                'user_id' => $user->id,
                'kind' => TaskRound::KIND_MISTAKES,
                'numbers' => $parent->numbers,
                'blocks' => $parent->blocks,
                'items' => $ids,
                'results' => [],
                'total' => count($ids),
                'day' => $this->today(),
                'parent_id' => $parent->id,
            ]);
        });
    }

    private function abandonOpen(User $user): void
    {
        TaskRound::query()
            ->where('user_id', $user->id)
            ->where('status', TaskRound::IN_PROGRESS)
            ->update(['status' => TaskRound::ABANDONED, 'updated_at' => now()]);
    }

    /*
    |--------------------------------------------------------------------------
    | Экран раунда
    |--------------------------------------------------------------------------
    */

    /** Задания раунда для страницы — без ответов и разборов. */
    public function cards(TaskRound $round): array
    {
        $tasks = Task::whereIn('id', $round->items)->get()->keyBy('id');
        $cards = [];
        foreach ($round->items as $i => $id) {
            $t = $tasks[$id] ?? null;
            if (! $t) {
                continue;
            }
            $cards[] = [
                'position' => $i,
                'number' => (int) $t->number,
                'block' => $t->block,
                'type' => $t->type,
                'question' => $t->question_text,
                'options' => $t->options ?: [],
                'matches' => $t->type === 'matching' ? $t->matches : null,
                'left_title' => $t->left_title,
                'right_title' => $t->right_title,
                'image' => $t->image_path ? asset('storage/'.$t->image_path) : null,
                'max' => (int) $t->max_score,
            ];
        }

        return $cards;
    }

    /*
    |--------------------------------------------------------------------------
    | Ответ
    |--------------------------------------------------------------------------
    */

    /**
     * Ответ на задание position. null — позиция не текущая (страница устарела).
     *
     * mode: check — первая попытка. Верно — задание закрыто. Не на полный
     * балл — ответ «висит» (pending): ученик выбирает «Перерешать» (retry,
     * за верный — на балл меньше, но не меньше первой попытки) или
     * «Посмотреть разбор» (reveal, засчитывается первая попытка). Пока
     * висит, верный ответ и разбор не отдаём. Повтор запроса закрытого
     * задания отдаёт тот же разбор.
     */
    public function answer(TaskRound $round, int $position, ?string $answer, ?int $ms = null, string $mode = self::CHECK): ?array
    {
        return DB::transaction(function () use ($round, $position, $answer, $ms, $mode) {
            $locked = TaskRound::whereKey($round->id)->lockForUpdate()->first();
            $results = $locked->results ?? [];
            $pending = $results[$position]['pending'] ?? false;

            if (isset($results[$position]) && ! $pending) {
                return $this->replay($locked, $position);
            }
            if ($locked->status !== TaskRound::IN_PROGRESS || $position !== (int) $locked->position) {
                return null;
            }

            $task = Task::findOrFail($locked->items[$position]);

            if ($pending) {
                $first = $results[$position];
                if ($mode === self::REVEAL) {
                    return $this->close($locked, $task, $position, ['answer' => $first['answer'], 'score' => (int) $first['score'], 'max' => (int) $first['max']], false);
                }
                if ($mode !== self::RETRY) {
                    return $this->pendingReply($locked, $position);
                }
                $graded = $this->attempt($locked, $task, $position, $answer, $ms);
                $score = max((int) $first['score'], (int) $graded['score'] - (int) config('task_trainer.retry_penalty'));

                return $this->close($locked, $task, $position, [
                    'answer' => $graded['answer'], 'score' => $score, 'max' => (int) $graded['max'],
                    'first' => $first['answer'], 'second' => $graded['full'] ? 'right' : ($graded['score'] > 0 ? 'partial' : 'wrong'),
                ], false);
            }

            if ($mode !== self::CHECK) {
                return null;
            }
            $graded = $this->attempt($locked, $task, $position, $answer, $ms);
            if ($graded['full']) {
                return $this->close($locked, $task, $position, ['answer' => $graded['answer'], 'score' => (int) $graded['score'], 'max' => (int) $graded['max']], true);
            }

            $results[$position] = ['pending' => true, 'answer' => $graded['answer'], 'score' => (int) $graded['score'], 'max' => (int) $graded['max']];
            $locked->results = $results;
            $locked->save();

            return $this->pendingReply($locked, $position);
        });
    }

    public const CHECK = 'check';

    public const RETRY = 'retry';

    public const REVEAL = 'reveal';

    /** Проверка одной попытки + запись в task_attempts. */
    private function attempt(TaskRound $round, Task $task, int $position, ?string $answer, ?int $ms): array
    {
        $answer = preg_replace('/\D/', '', (string) $answer);
        $graded = $this->grader->scoreOne($task, $answer);
        $score = (int) $graded['score'];
        $max = (int) $graded['max'];

        TaskAttempt::create([
            'task_id' => $task->id,
            'user_id' => $round->user_id,
            'round_id' => $round->id,
            'position' => $position,
            'answer' => $answer,
            'status' => $graded['status'],
            'score' => $score,
            'ms' => $ms !== null ? max(0, min($ms, 3_600_000)) : null,
        ]);

        return ['answer' => $answer, 'score' => $score, 'max' => $max, 'full' => $max > 0 && $score >= $max];
    }

    /** Закрыть задание: расписание, баллы раунда, переход дальше. full — верно с первой попытки. */
    private function close(TaskRound $round, Task $task, int $position, array $res, bool $full): array
    {
        $wasWrong = $this->recordProgress($round, $task, $full);

        $results = $round->results ?? [];
        $results[$position] = $res + ['again' => $full && $wasWrong];
        $round->results = $results;
        $round->position = $position + 1;
        $round->score = (int) $round->score + (int) $res['score'];
        $round->max_score = (int) $round->max_score + (int) $res['max'];

        if ($round->position >= $round->total) {
            $this->finish($round);
        }
        $round->save();

        return $this->replay($round, $position);
    }

    /** Висящий ответ на текущем задании (для перезагрузки страницы) или null. */
    public function pendingNow(TaskRound $round): ?array
    {
        $position = (int) $round->position;

        return ($round->results[$position]['pending'] ?? false) && $round->status === TaskRound::IN_PROGRESS
            ? $this->pendingReply($round, $position)
            : null;
    }

    /** Ответ «не на полный балл, выбирай» — без верного ответа и разбора. */
    private function pendingReply(TaskRound $round, int $position): array
    {
        $res = $round->results[$position];
        $max = (int) $res['max'];

        return [
            'pending' => true,
            'result' => [
                'position' => $position,
                'status' => (int) $res['score'] > 0 ? 'partial' : 'wrong',
                'score' => (int) $res['score'],
                'max' => $max,
                'answer' => $res['answer'],
                'retry_max' => max((int) $res['score'], $max - (int) config('task_trainer.retry_penalty')),
                'say' => Arr::random(config('task_trainer.mascot.pending_'.((int) $res['score'] > 0 ? 'partial' : 'wrong'))),
            ],
            'finished' => false,
            'summary' => null,
        ];
    }

    /** true — раньше по заданию была ошибка. Расписание — только в обычном раунде. */
    private function recordProgress(TaskRound $round, Task $task, bool $full): bool
    {
        $row = UserTask::where('user_id', $round->user_id)->where('task_id', $task->id)->lockForUpdate()->first();
        $wasWrong = $row !== null && ! $row->last_correct;

        if ($round->kind !== TaskRound::KIND_REGULAR) {
            return $wasWrong;
        }

        $next = $this->schedule->after($row?->step, $row?->due_on?->toDateString(), $full, $this->today());
        $row ??= new UserTask(['user_id' => $round->user_id, 'task_id' => $task->id]);
        $row->step = $next['step'];
        $row->due_on = $next['due_on'];
        $row->right_count = (int) $row->right_count + ($full ? 1 : 0);
        $row->wrong_count = (int) $row->wrong_count + ($full ? 0 : 1);
        $row->last_correct = $full;
        $row->last_answered_at = now();
        $row->save();

        return $wasWrong;
    }

    private function finish(TaskRound $round): void
    {
        $round->status = TaskRound::FINISHED;
        $round->finished_at = now();

        if ($round->kind !== TaskRound::KIND_REGULAR || $round->day->toDateString() !== $this->today()) {
            return;
        }

        $corm = $this->cormFor((int) $round->score, (int) $round->max_score, $this->cormToday((int) $round->user_id));
        // Корм общий для всех тренажёров — то же начисление, что у терминов.
        $this->fish->awardTermSession(User::find($round->user_id), $corm);
        $round->corm_awarded = $corm;
    }

    /** Корм за раунд с учётом того, сколько раундов «с полным кормом» уже было сегодня. */
    public function cormFor(int $score, int $max, array $today): int
    {
        if ($today['full_left'] > 0) {
            return $this->reward->corm($score, $max);
        }

        return $max > 0 && $score * 100 >= (int) config('task_trainer.overtime_min') * $max
            ? (int) config('task_trainer.overtime_corm')
            : 0;
    }

    /**
     * Корм за сегодня. «Полные» раунды — первые corm_full_rounds раундов,
     * которые принесли корм; дальше — сверхурочные.
     *
     * @return array{full: int, full_left: int, paid: int, earned: int}
     */
    public function cormToday(int $userId): array
    {
        $paid = TaskRound::query()
            ->where('user_id', $userId)
            ->where('kind', TaskRound::KIND_REGULAR)
            ->whereDate('day', $this->today())
            ->where('corm_awarded', '>', 0)
            ->get(['corm_awarded']);
        $full = (int) config('task_trainer.corm_full_rounds');

        return [
            'full' => $full,
            'full_left' => max(0, $full - $paid->count()),
            'paid' => $paid->count(),
            'earned' => (int) $paid->sum('corm_awarded'),
        ];
    }

    private function cormNote(TaskRound $round): ?string
    {
        $texts = config('task_trainer.texts');
        if ($round->kind !== TaskRound::KIND_REGULAR) {
            return $texts['corm_mistakes'];
        }
        if ($round->day->toDateString() !== $this->today()) {
            return null;
        }
        $today = $this->cormToday((int) $round->user_id);
        $min = (int) config('task_trainer.overtime_min');
        if ((int) $round->corm_awarded > 0) {
            return $today['full_left'] > 0
                ? strtr($texts['corm_after_left'], [':rounds' => \App\Support\Text::plural($today['full_left'], ['раунд', 'раунда', 'раундов'])])
                : strtr($texts['corm_after_last'], [':min' => $min]);
        }

        $lowest = min(array_keys(config('task_trainer.corm')));

        return $today['full_left'] > 0
            ? strtr($texts['corm_after_score'], [':min' => $lowest])
            : strtr($texts['corm_after_overtime'], [':min' => $min]);
    }

    /** Разбор задания position (и итог, если раунд закончен). */
    private function replay(TaskRound $round, int $position): array
    {
        $task = Task::find($round->items[$position]);
        $res = $round->results[$position];
        // Со второй попытки вердикт — по самой попытке («Верно»), а баллы — уже со штрафом.
        $status = $res['second'] ?? ($res['score'] >= $res['max'] && $res['max'] > 0 ? 'right' : ($res['score'] > 0 ? 'partial' : 'wrong'));

        return [
            'result' => [
                'position' => $position,
                'status' => $status,
                'second' => isset($res['second']),
                'score' => (int) $res['score'],
                'max' => (int) $res['max'],
                'answer' => $res['answer'],
                'correct' => (string) $task->answer,
                'order_matters' => $task->type === 'matching',
                'explanation' => $task->explanation,
                'say' => Arr::random(config('task_trainer.mascot.'.$status)),
                'task_id' => $task->id,
            ],
            'finished' => $round->isFinished(),
            'summary' => $round->isFinished() ? $this->summary($round) : null,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Итог
    |--------------------------------------------------------------------------
    */

    public function summary(TaskRound $round): array
    {
        $tasks = Task::whereIn('id', $round->items)->get()->keyBy('id');
        $numbers = [];
        $mistakes = [];
        foreach ($round->items as $i => $id) {
            $t = $tasks[$id] ?? null;
            $res = $round->results[$i] ?? null;
            if (! $t || ! $res) {
                continue;
            }
            $n = (int) $t->number;
            $numbers[$n] ??= ['n' => $n, 'title' => config('task_trainer.numbers.'.$n), 'score' => 0, 'max' => 0];
            $numbers[$n]['score'] += (int) $res['score'];
            $numbers[$n]['max'] += (int) $res['max'];
            if ((int) $res['score'] < (int) $res['max']) {
                $mistakes[] = [
                    'number' => $n,
                    'type' => $t->type,
                    'question' => $t->question_text,
                    'answer' => $res['answer'],
                    'correct' => (string) $t->answer,
                    'score' => (int) $res['score'],
                    'max' => (int) $res['max'],
                    'explanation' => $t->explanation,
                ];
            }
        }
        ksort($numbers);

        $score = (int) $round->score;
        $max = (int) $round->max_score;
        $key = match (true) {
            $max > 0 && $score >= $max => 'summary_top',
            $score * 3 >= $max * 2 => 'summary_good',
            default => 'summary_low',
        };

        return [
            'kind' => $round->kind,
            'score' => $score,
            'max' => $max,
            'total' => (int) $round->total,
            'corm' => (int) $round->corm_awarded,
            'corm_note' => $this->cormNote($round),
            'say' => Arr::random(config('task_trainer.mascot.'.$key)),
            'numbers' => array_values($numbers),
            'mistakes' => $mistakes,
        ];
    }
}
