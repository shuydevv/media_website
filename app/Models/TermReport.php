<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * «Сообщить об ошибке» из квиза по признакам: какой признак был верным и
 * какие три дистрактора ученик видел. Повторяющиеся репорты на один признак
 * — повод добавить ему group в базе терминов.
 */
class TermReport extends Model
{
    protected $fillable = ['user_id', 'term_id', 'feature_id', 'distractor_ids', 'comment', 'resolved_at'];

    protected $casts = [
        'distractor_ids' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function term()
    {
        return $this->belongsTo(Term::class);
    }

    public function feature()
    {
        return $this->belongsTo(TermFeature::class, 'feature_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
