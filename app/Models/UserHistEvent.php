<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Прогресс ученика по одному событию или правлению в тренажёре дат: шаг
 * сетки и дата следующего повторения. Строка появляется, когда элемент
 * изучен. Устроено как UserTerm.
 */
class UserHistEvent extends Model
{
    public const METHOD_YEAR = 'a';

    public const METHOD_QUIZ = 'b';

    protected $fillable = [
        'user_id', 'event_id', 'step', 'due_on', 'learned_on', 'last_reviewed_on', 'next_method', 'last_failed',
    ];

    protected $casts = [
        'due_on' => 'date',
        'learned_on' => 'date',
        'last_reviewed_on' => 'date',
        'last_failed' => 'boolean',
    ];

    public function event()
    {
        return $this->belongsTo(HistEvent::class, 'event_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
