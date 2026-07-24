<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Самостоятельное прорешивание задания из банка в личном кабинете — один
 * вопрос за раз (см. Student\TaskPracticeController), не через Submission.
 */
class TaskAttempt extends Model
{
    protected $fillable = [
        'task_id',
        'user_id',
        'answer',
        'status',
        'score',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
