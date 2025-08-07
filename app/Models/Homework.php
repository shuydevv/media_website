<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Homework extends Model
{
    use HasFactory;

    protected $table = 'homeworks';
    protected $fillable = ['title', 'description', 'type', 'course_id', 'lesson_id'];

    public function tasks()
    {
        return $this->hasMany(HomeworkTask::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class); // Связь с курсом
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

}
