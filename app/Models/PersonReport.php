<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Жалоба ученика из задания тренажёра личностей: личность и показанные варианты. */
class PersonReport extends Model
{
    protected $fillable = ['user_id', 'person_id', 'step', 'option_ids', 'comment', 'resolved_at'];

    protected $casts = [
        'option_ids' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
