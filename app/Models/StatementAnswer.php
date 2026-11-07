<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Один ответ на карточку — лог для аналитики, не для логики тренажёра. */
class StatementAnswer extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'session_id', 'user_id', 'statement_id', 'position', 'answer', 'correct', 'who', 'ms',
    ];

    protected $casts = [
        'correct' => 'boolean',
    ];
}
