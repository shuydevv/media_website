<?php

namespace App\Http\Controllers\Admin\Homework;

use App\Http\Controllers\Controller;
use App\Models\Homework;

class ShowController extends Controller
{
    public function __invoke(Homework $homework)
    {
        $homework->load('tasks', 'course', 'lesson.courseSession'); // или tasks, если ты переименуешь

        // Блок "открыть доступ ученику, записавшемуся позже" имеет смысл,
        // только если у урока вообще есть дата сессии — без неё
        // isLessonBeforeEnrollment() всегда false и блокировать некого
        // (см. Homework::isLessonBeforeEnrollment()).
        $lateEnrollmentRows = collect();
        $lessonDate = $homework->lesson?->courseSession?->start_date_time;

        if ($lessonDate !== null && $homework->course) {
            $unlockedUserIds = $homework->unlocks()->pluck('user_id')->all();

            $lateEnrollmentRows = $homework->course->students()
                ->orderBy('name')
                ->get()
                ->map(function ($student) use ($lessonDate, $unlockedUserIds, $homework) {
                    $enrolledAt = $student->courseEnrolledAt($homework->course_id);
                    $blocked = $enrolledAt !== null && $lessonDate->lt($enrolledAt);

                    return [
                        'student'    => $student,
                        'enrolledAt' => $enrolledAt,
                        'blocked'    => $blocked,
                        'unlocked'   => in_array($student->id, $unlockedUserIds, true),
                    ];
                })
                // Показываем только тех, кого урок реально касается: либо
                // сейчас заблокирован, либо уже точечно разблокирован
                // (чтобы можно было отозвать доступ) — остальные студенты
                // курса просто не интересны на этой странице.
                ->filter(fn (array $row) => $row['blocked'] || $row['unlocked'])
                ->values();
        }

        return view('admin.homeworks.show', compact('homework', 'lateEnrollmentRows'));
    }
}
