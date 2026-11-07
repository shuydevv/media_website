<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Прогресс ученика по заданию первой части (повтор ошибок, см. TaskTrainer). */
class UserTask extends Model
{
    protected $fillable = [
        'user_id', 'task_id', 'step', 'due_on', 'right_count', 'wrong_count',
        'last_correct', 'last_answered_at',
    ];

    protected $casts = [
        'due_on' => 'date',
        'last_correct' => 'boolean',
        'last_answered_at' => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }
}
