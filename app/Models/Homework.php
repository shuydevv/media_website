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

    /**
     * Число разрешённых попыток сдачи — 2 по умолчанию (столбец
     * attempts_allowed и заведён с default(2), см. миграцию add_score_and_
     * attempts), 0/null трактуем так же, а не как "безлимит": такого режима
     * в продукте нет. Единственный источник истины — раньше
     * SubmissionController::create() и submissions/show.blade.php считали
     * это по-разному (один разрешал безлимит, другой давал ровно 2), из-за
     * чего "Перерешать работу" могла быть недоступна, хотя бэкенд ещё
     * разрешил бы попытку.
     */
    public static function normalizeAttemptsAllowed($raw): int
    {
        $value = (int) ($raw ?? 0);

        return $value > 0 ? $value : 2;
    }

    public function attemptsAllowed(): int
    {
        return self::normalizeAttemptsAllowed($this->attempts_allowed);
    }
}
