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
    'passage_text',
    'answer',
    'options',
    'matches',
    'table_content',
    'media_path',
    'task_number',
    'task_id',
    'order',
    'image_auto_options',
    'image_auto_strict',
    'order_matters',
    'left_title',
    'right_title',
    'max_score', 
    ];

    protected $casts = [
        'options'        => 'array',
        'table_content'  => 'array',
        'order_matters'  => 'boolean',
        'matches' => 'array',
        'table' => 'array',
        'image_auto_options' => 'array',
    ];

    public const TYPE_TEST               = 'test';
    public const TYPE_TEXT_WITH_Q        = 'text_with_questions';
    public const TYPE_MATCHING           = 'matching';
    public const TYPE_IMAGE_AUTO         = 'image_auto';
    public const TYPE_IMAGE_MANUAL       = 'image_manual';
    public const TYPE_WRITTEN            = 'written';
    public const TYPE_TABLE              = 'table';

    public function isAutoGradable(): bool
{
    return in_array($this->type, [
        self::TYPE_TEST,
        self::TYPE_TEXT_WITH_Q,
        self::TYPE_MATCHING,
        self::TYPE_IMAGE_AUTO,
        self::TYPE_TABLE,
    ], true);
}

    public function homework()
    {
        return $this->belongsTo(Homework::class);
    }

    public function task()
    {
        return $this->belongsTo(\App\Models\Task::class);
    }
}
