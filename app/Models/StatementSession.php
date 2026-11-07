<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Сессия «Карточек суждений»: 15 карточек по выбранным блокам (regular) или
 * повтор ошибок только что законченной сессии (mistakes).
 */
class StatementSession extends Model
{
    public const KIND_REGULAR = 'regular';

    public const KIND_MISTAKES = 'mistakes';

    public const IN_PROGRESS = 'in_progress';

    public const FINISHED = 'finished';

    public const ABANDONED = 'abandoned';

    protected $fillable = [
        'user_id', 'kind', 'blocks', 'items', 'results', 'position', 'total', 'correct',
        'streak', 'best_streak', 'status', 'day', 'corm_awarded', 'parent_id', 'finished_at',
    ];

    protected $casts = [
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
