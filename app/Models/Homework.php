<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Homework extends Model
{
    use HasFactory;

    protected $table = 'homeworks';
    protected $fillable = ['title', 'description', 'type', 'course_id', 'lesson_id', 'attempts_allowed', 'due_at',];

    protected $casts = [
        'due_at' => 'datetime', // удобно форматировать
    ];

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

    public function submissions()
    {
        return $this->hasMany(\App\Models\Submission::class);
    }

    /**
     * Урок, к которому привязана домашка, ещё не наступил — до этого момента
     * ученик вообще не должен знать о существовании домашки (не в расписании,
     * не в списке домашек, и напрямую по ссылке зайти тоже нельзя). Если
     * домашка ни к какому уроку не привязана, или у урока не определена
     * дата/время сессии — ничего не можем утверждать, поэтому не прячем.
     */
    public function isLessonUpcoming(): bool
    {
        $session = $this->lesson?->courseSession;

        return $session !== null
            && $session->start_date_time !== null
            && now()->lt($session->start_date_time);
    }
}
