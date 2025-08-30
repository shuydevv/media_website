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
    ];

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

    $manualTypes = ['written','image_written','image_manual'];
    return $items->filter(fn($t) => in_array(($t->type ?? ''), $manualTypes, true))->values()->all();
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
