<?php

namespace App\Service\Bank;

use App\Models\Task;
use App\Models\TaskReview;
use App\Models\User;
use App\Service\FishFoodService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Станция «Задания», вторая часть: подходы, самопроверка по образцу,
 * отправка куратору и его проверка. Механика — docs/bank/Задания —
 * механика.md. Самопроверка корма не даёт; за ответ, проверенный
 * куратором, корм = балл куратора (как за домашку).
 */
class WrittenPractice
{
    /** Ответ короче — отправлять куратору нечего. */
    public const MIN_LENGTH = 40;

    public const MAX_LENGTH = 20000;

    /** Сколько минут куратор держит ответ за собой. */
    public const LOCK_MINUTES = 30;

    public function __construct(private FishFoodService $fish)
    {
    }

    public function today(): string
    {
        return now()->toDateString();
    }

    /*
    |--------------------------------------------------------------------------
    | Обзор и старт (ученик)
    |--------------------------------------------------------------------------
    */

    /** @return array{draft: ?TaskReview, reviews: \Illuminate\Support\Collection, sends_left: int, solved: array} */
    public function overview(User $user): array
    {
        $solved = TaskReview::query()
            ->join('tasks', 'tasks.id', '=', 'task_reviews.task_id')
            ->where('task_reviews.user_id', $user->id)
            ->where('task_reviews.status', '!=', TaskReview::DRAFT)
            ->selectRaw('tasks.number, count(*) as n')
            ->groupBy('tasks.number')
            ->pluck('n', 'number');

        return [
            'draft' => $this->draft($user),
            'reviews' => TaskReview::with('task')
                ->where('user_id', $user->id)
                ->whereIn('status', [TaskReview::SENT, TaskReview::CHECKED])
                ->orderByRaw('case when status = ? and seen_at is null then 0 else 1 end', [TaskReview::CHECKED])
                ->latest('sent_at')
                ->limit(20)
                ->get(),
            'sends_left' => $this->sendsLeft($user),
            'solved' => $solved->mapWithKeys(fn ($n, $k) => [(int) $k => (int) $n])->all(),
        ];
    }

    public function draft(User $user): ?TaskReview
    {
        return TaskReview::with('task')->where('user_id', $user->id)->where('status', TaskReview::DRAFT)->latest('id')->first();
    }

    public function sendsLeft(User $user): int
    {
        $sent = TaskReview::where('user_id', $user->id)->whereDate('sent_at', $this->today())->count();

        return max(0, (int) config('task_trainer.reviews_per_day') - $sent);
    }

    /**
     * Подход по номеру: незаконченный черновик того же номера — продолжаем,
     * иначе новое задание (сначала нерешённые). 17 и 18 с общим текстом —
     * одним подходом. null — под выбор нет заданий.
     */
    public function start(User $user, int $number, array $blocks = []): ?TaskReview
    {
        if (! in_array($number, TaskTrainer::part2Numbers(), true)) {
            return null;
        }
        $blocks = array_values(array_intersect(array_keys(config('statements.blocks')), $blocks));

        return DB::transaction(function () use ($user, $number, $blocks) {
            User::whereKey($user->id)->lockForUpdate()->first();

            $draft = $this->draft($user);
            if ($draft && (int) $draft->task->number === $number) {
                return $draft;
            }

            $base = fn () => Task::forTrainer()
                ->where('number', (string) $number)
                ->when($blocks, fn ($q) => $q->whereIn('block', $blocks));
            $done = TaskReview::where('user_id', $user->id)->pluck('items')->flatten()->map(fn ($id) => (int) $id)->unique()->all();

            $task = $base()->whereNotIn('id', $done ?: [0])->inRandomOrder()->first()
                ?? $base()->inRandomOrder()->first();
            if (! $task) {
                return null;
            }

            $items = collect([$task]);
            if ($task->group_key && in_array($number, [17, 18], true)) {
                $items = Task::forTrainer()->where('group_key', $task->group_key)->whereIn('number', ['17', '18'])
                    ->get()->sortBy(fn (Task $t) => (int) $t->number)->unique('number')->values();
            }

            // Старый черновик другого номера — выбрасываем: он не отправлен
            // и образца ученик не видел.
            if ($draft) {
                $draft->delete();
            }

            return TaskReview::create([
                'user_id' => $user->id,
                'task_id' => $items->first()->id,
                'items' => $items->pluck('id')->all(),
                'answers' => [],
                'status' => TaskReview::DRAFT,
                'max_score' => $items->sum(fn (Task $t) => (int) $t->max_score),
            ]);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Черновик, самопроверка, отправка (ученик)
    |--------------------------------------------------------------------------
    */

    /** @param array<int|string, ?string> $answers task_id => текст */
    public function saveDraft(TaskReview $review, array $answers): void
    {
        if ($review->status !== TaskReview::DRAFT) {
            return;
        }
        $review->answers = $this->cleanAnswers($review, $answers);
        $review->save();
    }

    public function selfCheck(TaskReview $review, array $answers): void
    {
        DB::transaction(function () use ($review, $answers) {
            $locked = TaskReview::whereKey($review->id)->lockForUpdate()->first();
            if ($locked->status !== TaskReview::DRAFT) {
                return;
            }
            $locked->answers = $this->cleanAnswers($locked, $answers);
            $locked->status = TaskReview::SELF;
            $locked->save();
        });
    }

    /** @throws ValidationException лимит на день или пустой ответ */
    public function send(TaskReview $review, array $answers): void
    {
        DB::transaction(function () use ($review, $answers) {
            $user = User::whereKey($review->user_id)->lockForUpdate()->first();
            $locked = TaskReview::whereKey($review->id)->lockForUpdate()->first();
            if ($locked->status !== TaskReview::DRAFT) {
                return;
            }
            $clean = $this->cleanAnswers($locked, $answers);
            $texts = config('task_trainer.texts');
            foreach ($locked->items as $id) {
                if (mb_strlen(trim($clean[(string) $id] ?? '')) < self::MIN_LENGTH) {
                    throw ValidationException::withMessages(['answers' => $texts['too_short']]);
                }
            }
            if ($this->sendsLeft($user) <= 0) {
                throw ValidationException::withMessages(['answers' => $texts['send_limit']]);
            }
            $locked->answers = $clean;
            $locked->status = TaskReview::SENT;
            $locked->sent_at = now();
            $locked->save();
        });
    }

    private function cleanAnswers(TaskReview $review, array $answers): array
    {
        $clean = [];
        foreach ($review->items as $id) {
            $text = (string) ($answers[$id] ?? $answers[(string) $id] ?? '');
            $clean[(string) $id] = mb_substr(str_replace("\r\n", "\n", $text), 0, self::MAX_LENGTH);
        }

        return $clean;
    }

    /**
     * Отметки чек-листа: {task_id: {items: [балл…], penalty: 0|1|2}}.
     * Можно после сверки и после отправки (сравним с куратором).
     */
    public function saveMarks(TaskReview $review, array $marks): void
    {
        if (! $review->exemplarOpen()) {
            return;
        }
        $tasks = $review->tasks()->keyBy('id');
        $clean = [];
        $total = 0;
        foreach ($tasks as $id => $task) {
            $m = $marks[$id] ?? $marks[(string) $id] ?? [];
            $items = array_map('intval', array_values((array) ($m['items'] ?? [])));
            $penalty = (int) ($m['penalty'] ?? 0);
            $clean[(string) $id] = ['items' => $items, 'penalty' => $penalty];
            $total += self::scoreFor((int) $task->number, $items, $penalty, (int) $task->max_score);
        }
        $review->self_marks = $clean;
        $review->self_score = $total;
        $review->save();
    }

    /** Балл по отметкам чек-листа номера (config task_selfcheck). */
    public static function scoreFor(int $number, array $items, int $penalty, int $max): int
    {
        $spec = config('task_selfcheck.'.$number);
        if (! $spec) {
            return 0;
        }
        $marks = [];
        foreach ($spec['items'] as $i => $item) {
            $marks[$i] = max(0, min((int) ($item['max'] ?? 1), (int) ($items[$i] ?? 0)));
        }
        if (isset($spec['gate'])) {
            foreach ($spec['gate']['needs'] as $i => $need) {
                if (($marks[$i] ?? 0) < $need) {
                    $marks[$spec['gate']['item']] = 0;
                }
            }
        }
        $sum = array_sum($marks);
        if (isset($spec['scale'])) {
            $sum = $spec['scale'][$sum] ?? max($spec['scale']);
        }
        if (isset($spec['penalty'])) {
            $sum = $penalty >= 2 ? 0 : ($penalty === 1 ? $sum - 1 : $sum);
        }

        return max(0, min($max, $sum));
    }

    public function markSeen(TaskReview $review): void
    {
        if ($review->status === TaskReview::CHECKED && ! $review->seen_at) {
            $review->seen_at = now();
            $review->save();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Куратор
    |--------------------------------------------------------------------------
    */

    /** Ждут проверки: не заняты другим куратором, самые давние первыми. */
    public function queue(int $mentorId)
    {
        return TaskReview::with(['user', 'task'])
            ->where('status', TaskReview::SENT)
            // Ответы демо-ученика (показ курса) куратору не нужны.
            ->whereHas('user', fn ($q) => $q->where('is_demo', false))
            ->where(fn ($q) => $q->whereNull('locked_by')
                ->orWhere('locked_by', $mentorId)
                ->orWhere('lock_expires_at', '<', now()))
            ->orderBy('sent_at')
            ->get();
    }

    /** Взять на проверку. false — уже держит другой куратор. */
    public function lock(TaskReview $review, User $mentor): bool
    {
        return DB::transaction(function () use ($review, $mentor) {
            $locked = TaskReview::whereKey($review->id)->lockForUpdate()->first();
            if ($locked->isLockedByOther((int) $mentor->id)) {
                return false;
            }
            $locked->locked_by = $mentor->id;
            $locked->lock_expires_at = now()->addMinutes(self::LOCK_MINUTES);
            $locked->save();
            $review->setRawAttributes($locked->getAttributes(), true);

            return true;
        });
    }

    public function unlock(TaskReview $review, User $mentor): void
    {
        if ((int) $review->locked_by === (int) $mentor->id) {
            $review->forceFill(['locked_by' => null, 'lock_expires_at' => null])->save();
        }
    }

    /**
     * Оценка куратора. Повторная оценка (поправил балл) пересчитывает корм
     * на разницу — как syncTaskCorm() у домашек.
     *
     * @param  array<int|string, int>  $scores  task_id => балл
     */
    public function grade(TaskReview $review, User $mentor, array $scores, ?string $comment): void
    {
        DB::transaction(function () use ($review, $mentor, $scores, $comment) {
            $locked = TaskReview::whereKey($review->id)->lockForUpdate()->first();
            if (! in_array($locked->status, [TaskReview::SENT, TaskReview::CHECKED], true)) {
                return;
            }
            if ($locked->isLockedByOther((int) $mentor->id)) {
                throw ValidationException::withMessages(['scores' => 'Этот ответ сейчас проверяет другой куратор.']);
            }
            $clean = [];
            $total = 0;
            foreach ($locked->tasks() as $task) {
                $s = max(0, min((int) $task->max_score, (int) ($scores[$task->id] ?? $scores[(string) $task->id] ?? 0)));
                $clean[(string) $task->id] = $s;
                $total += $s;
            }

            $row = ['score' => $total, 'fish_awarded' => (int) $locked->corm_awarded];
            $this->fish->syncTaskCorm(User::findOrFail($locked->user_id), $row);

            $locked->scores = $clean;
            $locked->score = $total;
            $locked->comment = $comment !== null && trim($comment) !== '' ? mb_substr(trim($comment), 0, 5000) : null;
            $locked->status = TaskReview::CHECKED;
            $locked->checked_by = $mentor->id;
            $locked->checked_at = now();
            $locked->seen_at = null;
            $locked->corm_awarded = (int) $row['fish_awarded'];
            $locked->locked_by = null;
            $locked->lock_expires_at = null;
            $locked->save();
        });
    }
}
