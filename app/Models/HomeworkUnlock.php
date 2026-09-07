<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Точечное исключение из Homework::isLessonBeforeEnrollment() — см.
 * комментарий в миграции create_homework_unlocks_table.
 */
class HomeworkUnlock extends Model
{
    protected $fillable = ['homework_id', 'user_id', 'granted_by'];

    public function homework()
    {
        return $this->belongsTo(Homework::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function grantedBy()
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
