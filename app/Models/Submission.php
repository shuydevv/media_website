<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class Submission extends Model
{
    protected $table = 'submissions';

    protected $fillable = [
        'homework_id',
        'user_id',
        'answers',
        'status',
        'comment',

        // новая логика
        'attempt_no',
        'autocheck_score',
        'manual_score',
        'total_score',
        'per_task_results',

        // таймер пробника
        'started_at',
        'expires_at',
        'submitted_at',

        // аналитика по заданиям для отчёта: время, неверные проверки, подсказки
        'task_meta',

        // блокировка ревью
        'locked_by',
        'lock_expires_at',

        'ai_drafts',
        'ai_frozen_hash',
    ];

    protected $casts = [
        'answers'          => 'array',
        'per_task_results' => 'array',
        'lock_expires_at'  => 'datetime',
        'ai_drafts'        => 'array',
        'ai_frozen_hash'   => 'array',
        'started_at'       => 'datetime',
        'expires_at'       => 'datetime',
        'submitted_at'     => 'datetime',
        'task_meta'        => 'array',
    ];

    /**
     * Таймер пробника истёк — проверяется реактивно (см. SubmissionController::
     * autoFinishIfExpired()), без фоновых джобов, по той же схеме, что и due_at.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && now()->isAfter($this->expires_at);
    }

    /*
    |--------------------------------------------------------------------------
    | Связи
    |--------------------------------------------------------------------------
    */

    public function homework()
    {
        return $this->belongsTo(Homework::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Куратор, который сейчас держит лок на этой попытке (см. locked_by). */
    public function lockedByUser()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Скоупы (для inbox и ревью)
    |--------------------------------------------------------------------------
    */

    /**
     * Заявки, доступные к ревью (статус submitted/approved, не залочены или просрочены).
     */
    public function scopeAvailableForReview(Builder $query): Builder
    {
        return $query->whereIn('status', ['submitted', 'approved'])
            ->where(function ($q) {
                $q->whereNull('locked_by')
                  ->orWhere('lock_expires_at', '<', now());
            });
    }

    /**
     * Заявки, которые залочены за данным пользователем (и не истёк срок).
     */
    public function scopeLockedBy(Builder $query, int $userId): Builder
    {
        return $query->where('locked_by', $userId)
                     ->where('lock_expires_at', '>', now());
    }

    /**
     * Очередь работ, актуальных для проверки куратором: не занятых чужим
     * активным локом, и без «устаревших» попыток — если студент успел
     * начать вторую попытку по той же домашке до того, как куратор
     * проверил первую, обе какое-то время висят как pending/expired, но
     * куратору нужна только последняя (именно по ней выставляется итог).
     *
     * Вынесено сюда из Mentor\SubmissionController::index() — та же
     * логика была продублирована (без dedup) в
     * Mentor\SubmissionReviewController::finishAndNext(), из-за чего после
     * проверки последней попытки куратору предлагалась следующая по
     * очереди — в том числе более ранняя, уже переигранная попытка того же
     * ученика по той же домашке.
     */
    public static function pendingReviewQueue(): \Illuminate\Support\Collection
    {
        $queue = static::query()
            ->with(['user', 'homework.tasks'])
            ->where(function (Builder $q) {
                $q->whereNull('locked_by')
                  ->orWhere('lock_expires_at', '<=', now());
            })
            ->whereIn('status', ['pending', 'expired'])
            ->orderBy('created_at')
            ->get()
            ->reject(fn (Submission $s) => $s->status === 'expired' && $s->allManualTasksClosedForMentor())
            ->sortBy('id')
            ->groupBy(fn (Submission $s) => $s->user_id . ':' . $s->homework_id)
            ->map->last()
            ->sortBy('created_at')
            ->values();

        return static::withoutSuperseded($queue);
    }

    /**
     * Убирает попытки, после которых ученик уже сдал более новую. Дедуп в
     * pendingReviewQueue() видит только попытки, которые сами ещё ждут
     * проверки: как только куратор закрывал вторую попытку (она уходила в
     * checked), первая — всё ещё pending — оставалась в группе одна и снова
     * всплывала в очереди. Проверять её незачем: итог по домашке считается
     * по последней сданной попытке. Незаконченная (in_progress) новая
     * попытка старую не перекрывает — ученик может её отменить.
     */
    public static function withoutSuperseded(\Illuminate\Support\Collection $submissions): \Illuminate\Support\Collection
    {
        if ($submissions->isEmpty()) {
            return $submissions;
        }

        $latestFinished = static::query()
            ->whereIn('user_id', $submissions->pluck('user_id')->unique())
            ->whereIn('homework_id', $submissions->pluck('homework_id')->unique())
            ->where('status', '!=', 'in_progress')
            ->groupBy('user_id', 'homework_id')
            ->selectRaw('user_id, homework_id, MAX(id) AS max_id')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->user_id . ':' . $r->homework_id => (int) $r->max_id]);

        return $submissions
            ->reject(fn (Submission $s) => ($latestFinished[$s->user_id . ':' . $s->homework_id] ?? 0) > $s->id)
            ->values();
    }

    /**
     * Можно ли ученику отменить эту незаконченную попытку (Student\SubmissionController::cancel()):
     *  - повторную попытку домашки — да: передумал перерешивать, в зачёте
     *    остаётся прошлая. Первую — нет: незаконченная она ни на что не
     *    влияет, а при отмене пропали бы ответы;
     *  - пробник — только пока не дан ни один ответ (зашёл посмотреть или
     *    случайно). Иначе можно было бы просмотреть все задания, отменить и
     *    начать заново с полным таймером.
     */
    public function canBeCancelled(): bool
    {
        if ($this->status !== 'in_progress') {
            return false;
        }

        if (Homework::whereKey($this->homework_id)->value('type') === 'mock') {
            return ($this->answers ?? []) === [];
        }

        return static::query()
            ->where('user_id', $this->user_id)
            ->where('homework_id', $this->homework_id)
            ->where('status', '!=', 'in_progress')
            ->where('id', '<', $this->id)
            ->exists();
    }

    /** Предыдущая сданная попытка ученика по этой домашке (для сравнения у куратора). */
    public function previousFinished(): ?self
    {
        return static::query()
            ->where('user_id', $this->user_id)
            ->where('homework_id', $this->homework_id)
            ->where('status', '!=', 'in_progress')
            ->where('id', '<', $this->id)
            ->latest('id')
            ->first();
    }

    /**
     * Начатая после этой, но ещё не сданная попытка. Куратору — повод
     * подождать: если ученик её отправит, эта попытка уйдёт из очереди и
     * проверять её будет незачем (см. withoutSuperseded()).
     */
    public function newerInProgress(): ?self
    {
        return static::query()
            ->where('user_id', $this->user_id)
            ->where('homework_id', $this->homework_id)
            ->where('status', 'in_progress')
            ->where('id', '>', $this->id)
            ->latest('id')
            ->first();
    }

    /** Есть ли у ученика более новая сданная попытка по этой же домашке. */
    public function isSuperseded(): bool
    {
        return static::query()
            ->where('user_id', $this->user_id)
            ->where('homework_id', $this->homework_id)
            ->where('status', '!=', 'in_progress')
            ->where('id', '>', $this->id)
            ->exists();
    }

    /**
     * Строка per_task_results перенесена из прошлой попытки при «Перерешать
     * ошибки» — задание в этой попытке не решалось заново (см.
     * App\Service\Homework\MistakesRetry).
     */
    public function isTaskCarried($taskId): bool
    {
        return ! empty(($this->per_task_results ?? [])[$taskId]['carried']);
    }

    /*
    |--------------------------------------------------------------------------
    | Утилиты
    |--------------------------------------------------------------------------
    */

    /**
     * Проверка: есть ли активный lock за этим submission.
     */
    public function isLocked(): bool
    {
        return $this->locked_by !== null
            && $this->lock_expires_at instanceof Carbon
            && $this->lock_expires_at->isFuture();
    }

    /**
     * Проверка: залочено ли другим пользователем.
     */
    public function isLockedByOther(int $userId): bool
    {
        return $this->isLocked() && $this->locked_by !== $userId;
    }

    /**
 * Вернуть массив объектов письменных задач (распарсить JSON при необходимости).
 */
public function getManualTasks(): array
{
    $tasksRaw = $this->homework->tasks ?? [];
    if (is_string($tasksRaw)) {
        $decoded = json_decode($tasksRaw, true);
        $tasksRaw = is_array($decoded) ? $decoded : [];
    }
    $items = collect($tasksRaw)->map(function ($t) {
        if (is_array($t))  return (object)$t;
        if (is_object($t)) return $t;
        if (is_string($t)) {
            $one = json_decode($t, true);
            return (object)($one ?: []);
        }
        return (object)[];
    });

    return $items->filter(fn($t) => in_array(($t->type ?? ''), HomeworkTask::MANUAL_TYPES, true))->values()->all();
}

/**
 * Все ли письменные задания «закрыты» для МЕНТОРА:
 * — есть сохранённые данные ИЛИ стоит skipped.
 */
public function allManualTasksClosedForMentor(): bool
{
    $tasks = $this->getManualTasks();
    $per   = $this->per_task_results ?? [];

    foreach ($tasks as $idx => $t) {
        $tid = $t->id ?? ("t_manual_{$idx}");
        $row = $per[$tid] ?? null;

        $skipped   = (bool)($row['skipped'] ?? false);
        $hasAny    = $row && (
            array_key_exists('score', $row) ||
            array_key_exists('reason', $row) ||
            array_key_exists('comment', $row)
        );

        if (!($skipped || $hasAny)) {
            return false;
        }
    }
    return true;
}

/**
 * ИИ-статус работы для списка на /mentor/submissions: сколько письменных
 * заданий уже с черновиком ИИ (ready), сколько ждут ИИ-проверки — выгружены
 * в Claude Desktop или в пачке API (waiting), из всех письменных (total).
 *
 * @return array{ready:int, waiting:int, total:int}
 */
public function aiDraftSummary(): array
{
    $drafts = $this->ai_drafts ?? [];
    $ready = $waiting = 0;
    $tasks = $this->getManualTasks();

    foreach ($tasks as $idx => $t) {
        $draft = $drafts[$t->id ?? ("t_manual_{$idx}")] ?? [];
        if (isset($draft['score'])) {
            $ready++;
        } elseif (in_array($draft['status'] ?? null, ['exported', 'pending'], true)) {
            $waiting++;
        }
    }

    return ['ready' => $ready, 'waiting' => $waiting, 'total' => count($tasks)];
}

/**
 * Все ли письменные задания «закрыты» для АДМИНА:
 * — должны быть фактические данные (skipped НЕ считается закрытием).
 */
public function allManualTasksClosedForAdmin(): bool
{
    $tasks = $this->getManualTasks();
    $per   = $this->per_task_results ?? [];

    foreach ($tasks as $idx => $t) {
        $tid = $t->id ?? ("t_manual_{$idx}");
        $row = $per[$tid] ?? null;

        $hasAny = $row && (
            array_key_exists('score', $row) ||
            array_key_exists('reason', $row) ||
            array_key_exists('comment', $row)
        );

        if (!$hasAny) {
            return false;
        }
    }
    return true;
}

public function hasSkippedManualTasks(): bool
{
    $per = $this->per_task_results ?? [];
    foreach ($per as $row) {
        if (!empty($row['skipped'])) return true;
    }
    return false;
}
}
