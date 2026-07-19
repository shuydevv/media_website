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
    'image_path',
    'task_number',
    'task_id',
    'order',
    'image_auto_options',
    'image_auto_strict',
    'order_matters',
    'left_title',
    'right_title',
    'max_score',
    'hint',
    ];

    protected $casts = [
        'options'        => 'array',
        'table_content'  => 'array',
        'order_matters'  => 'boolean',
        'matches' => 'array',
        'table' => 'array',
        'image_auto_options' => 'array',
    ];

    /**
     * Единственный источник правды для "какие типы заданий проверяются
     * вручную куратором" — раньше этот список был продублирован в 6+ местах
     * (AutoGrader, SubmissionController, несколько Blade-view), а
     * isAutoGradable() ниже вместо этого сверялся с отдельным, независимым
     * набором TYPE_*-констант. Админ-форма сохраняла тип задания под
     * другими строками (например, 'multiple_choice' вместо 'test'), которые
     * не входили ни в один из списков TYPE_* — из-за этого isAutoGradable()
     * считал такие задания НЕ авто-проверяемыми и ученику показывался
     * textarea вместо квадратиков ответа, хотя тип был "Тест с вариантами".
     * Теперь и админ-форма, и эта проверка используют один и тот же список.
     */
    public const MANUAL_TYPES = ['written', 'image_written', 'image_manual'];

    public function isAutoGradable(): bool
    {
        return !in_array($this->type, self::MANUAL_TYPES, true);
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
