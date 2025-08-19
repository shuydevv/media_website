<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    // ВАЖНО: правильная таблица
    protected $table = 'submissions';

    protected $fillable = [
        'homework_id',
        'user_id',
        'attempt_no',
        'answers',
        'status',
        'autocheck_score',
        'manual_score',
        'total_score',
        'per_task_results',
    ];

    protected $casts = [
        'answers'          => 'array',
        'per_task_results' => 'array',
    ];

    // Связи (опционально, но корректные)
    public function homework()
    {
        return $this->belongsTo(Homework::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
