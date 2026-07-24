<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Критерии проверки и баллы для ОДНОГО номера задания в категории
 * (например, «Обществознание, № 25») — общие для всех вопросов банка с
 * этим номером: и критерии, и баллы за задание — свойство номера, не
 * конкретного вопроса. Точечное исключение для конкретного вопроса —
 * Task::criteria_override (только для критериев, у баллов такого нет).
 */
class TaskCriteria extends Model
{
    protected $table = 'task_criteria';

    protected $fillable = [
        'category_id',
        'number',
        'criteria',
        'ai_rationale_template',
        'comment',
        'max_score',
        'created_by',
        'updated_by',
    ];

    public function category()
    {
        return $this->belongsTo(\App\Models\Category::class);
    }

    protected static function booted(): void
    {
        static::creating(function (TaskCriteria $criteria) {
            if (auth()->check()) {
                $criteria->created_by = auth()->id();
                $criteria->updated_by = auth()->id();
            }
        });

        static::updating(function (TaskCriteria $criteria) {
            if (auth()->check()) {
                $criteria->updated_by = auth()->id();
            }
        });
    }
}
