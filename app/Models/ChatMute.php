<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Мьют ученика в чате трансляций: lesson_id = null — во всех эфирах,
 * until = null — без срока (для мьюта урока — до конца этого эфира).
 */
class ChatMute extends Model
{
    protected $fillable = ['user_id', 'lesson_id', 'until', 'created_by'];

    protected $casts = [
        'until' => 'datetime',
    ];

    /** Мьюты, действующие сейчас для этого урока. */
    public function scopeActiveFor(Builder $q, int $userId, int $lessonId): Builder
    {
        return $q->where('user_id', $userId)
            ->where(fn ($w) => $w->whereNull('lesson_id')->orWhere('lesson_id', $lessonId))
            ->where(fn ($w) => $w->whereNull('until')->orWhere('until', '>', now()));
    }
}
