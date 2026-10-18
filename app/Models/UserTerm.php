<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Прогресс ученика по одному термину: шаг сетки и дата следующего
 * повторения. Строка появляется, когда термин изучен; меняет её только
 * App\Service\Terms\TermSchedule.
 */
class UserTerm extends Model
{
    public const METHOD_SQUARES = 'a';

    public const METHOD_QUIZ = 'b';

    protected $fillable = [
        'user_id', 'term_id', 'step', 'due_on', 'learned_on', 'last_reviewed_on', 'next_method', 'last_failed',
    ];

    protected $casts = [
        'due_on' => 'date',
        'learned_on' => 'date',
        'last_reviewed_on' => 'date',
        'last_failed' => 'boolean',
    ];

    public function term()
    {
        return $this->belongsTo(Term::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
