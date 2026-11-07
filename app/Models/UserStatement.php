<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Прогресс ученика по одному суждению (см. Service\Statements\StatementSchedule). */
class UserStatement extends Model
{
    protected $fillable = [
        'user_id', 'statement_id', 'step', 'due_on', 'right_count', 'wrong_count',
        'last_correct', 'last_answered_at',
    ];

    protected $casts = [
        'due_on' => 'date',
        'last_correct' => 'boolean',
        'last_answered_at' => 'datetime',
    ];

    public function statement()
    {
        return $this->belongsTo(Statement::class);
    }
}
