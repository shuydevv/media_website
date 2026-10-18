<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Сессия тренажёра: изучение порции, повторение дня или свободная
 * тренировка. Состояние (очередь, фаза, первые попытки) лежит в state,
 * чтобы прерванную сессию можно было продолжить.
 */
class TermSession extends Model
{
    public const KIND_LEARN = 'learn';

    public const KIND_REVIEW = 'review';

    public const KIND_FREE = 'free';

    protected $fillable = [
        'user_id', 'kind', 'day', 'state', 'total', 'correct_first', 'corm_awarded', 'finished_at',
    ];

    protected $casts = [
        'day' => 'date',
        'state' => 'array',
        'finished_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
