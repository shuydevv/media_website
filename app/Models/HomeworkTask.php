<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class HomeworkTask extends Model
{
    use HasFactory;
    protected $table = 'homework_tasks';

    protected $fillable = [
        'homework_id',
        'type',
        'question_text',
        'options',
        'matches',
        'table',
        'image_path',
        'answer',
        'order',
        'task_number',
    ];

    protected $casts = [
        'options' => 'array',
        'matches' => 'array',
        'table'   => 'array',
    ];

    public function homework()
    {
        return $this->belongsTo(Homework::class);
    }
}
