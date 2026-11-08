<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Прогресс ученика по одной личности: шаг сетки, дата следующего повторения
 * и каким заданием спросить в следующий раз. Строка появляется, когда
 * личность изучена. Устроено как UserHistEvent.
 */
class UserPerson extends Model
{
    protected $table = 'user_persons';

    public const METHOD_TYPE = 'a';   // ввод фамилии в клетки

    public const METHOD_KNOWN = 'b';  // «Чем известен?»

    public const METHOD_WHO = 'c';    // «Кто это?»

    protected $fillable = [
        'user_id', 'person_id', 'step', 'due_on', 'learned_on', 'last_reviewed_on', 'next_method', 'last_failed',
    ];

    protected $casts = [
        'due_on' => 'date',
        'learned_on' => 'date',
        'last_reviewed_on' => 'date',
        'last_failed' => 'boolean',
    ];

    public function person()
    {
        return $this->belongsTo(Person::class);
    }
}
