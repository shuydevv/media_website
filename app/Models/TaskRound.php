<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Раунд первой части на станции «Задания»: 10 заданий выбранных номеров и
 * блоков (regular) или повтор ошибок только что законченного раунда
 * (mistakes — без корма и расписания).
 */
class TaskRound extends Model
{
    public const KIND_REGULAR = 'regular';

    public const KIND_MISTAKES = 'mistakes';

    public const IN_PROGRESS = 'in_progress';

    public const FINISHED = 'finished';

    public const ABANDONED = 'abandoned';

    protected $fillable = [
        'user_id', 'kind', 'numbers', 'blocks', 'items', 'results', 'position', 'total',
        'score', 'max_score', 'status', 'day', 'corm_awarded', 'parent_id', 'finished_at',
    ];

    protected $casts = [
        'numbers' => 'array',
        'blocks' => 'array',
        'items' => 'array',
        'results' => 'array',
        'day' => 'date',
        'finished_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isFinished(): bool
    {
        return $this->status === self::FINISHED;
    }
}
