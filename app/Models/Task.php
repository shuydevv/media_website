<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = [
        'category_id',
        'number',
        'criteria',
        'ai_rationale_template',
        'comment',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        // 'criteria' => 'array',
    ];

    public function category()
    {
        return $this->belongsTo(\App\Models\Category::class);
    }

    public function homeworkTasks()
    {
        return $this->hasMany(\App\Models\HomeworkTask::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Task $task) {
            if (auth()->check()) {
                $task->created_by = auth()->id();
                $task->updated_by = auth()->id();
            }
        });

        static::updating(function (Task $task) {
            if (auth()->check()) {
                $task->updated_by = auth()->id();
            }
        });
    }
}
