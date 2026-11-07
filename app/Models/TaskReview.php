<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Подход второй части на станции «Задания»: одно задание (или 17+18 с
 * общим текстом). Ученик пишет ответ (draft), дальше либо сверяется с
 * образцом сам (self — без корма), либо отправляет куратору (sent →
 * checked — корм = балл куратора). См. App\Service\Bank\WrittenPractice.
 */
class TaskReview extends Model
{
    public const DRAFT = 'draft';

    public const SELF = 'self';

    public const SENT = 'sent';

    public const CHECKED = 'checked';

    protected $fillable = [
        'user_id', 'task_id', 'items', 'answers', 'status', 'self_marks', 'self_score',
        'max_score', 'sent_at', 'scores', 'score', 'comment', 'checked_by', 'checked_at',
        'seen_at', 'locked_by', 'lock_expires_at', 'corm_awarded',
    ];

    protected $casts = [
        'items' => 'array',
        'answers' => 'array',
        'self_marks' => 'array',
        'scores' => 'array',
        'sent_at' => 'datetime',
        'checked_at' => 'datetime',
        'seen_at' => 'datetime',
        'lock_expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function checker()
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    /** Задания подхода по порядку (17, 18). */
    public function tasks()
    {
        $ids = array_map('intval', $this->items ?? []);

        return Task::whereIn('id', $ids)->get()->sortBy(fn (Task $t) => array_search($t->id, $ids, true))->values();
    }

    /** Образец виден: ученик сверился сам или уже отправил ответ. */
    public function exemplarOpen(): bool
    {
        return $this->status !== self::DRAFT;
    }

    public function isLockedByOther(int $userId): bool
    {
        return $this->locked_by && (int) $this->locked_by !== $userId
            && $this->lock_expires_at && $this->lock_expires_at->isFuture();
    }
}
