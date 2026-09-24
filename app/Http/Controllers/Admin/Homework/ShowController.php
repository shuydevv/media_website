<?php

namespace App\Http\Controllers\Admin\Homework;

use App\Http\Controllers\Controller;
use App\Models\Homework;
use App\Models\Submission;

class ShowController extends Controller
{
    public function __invoke(Homework $homework)
    {
        $homework->load('tasks', 'course', 'lesson.courseSession'); // или tasks, если ты переименуешь

        // Попытки учеников по этой домашке — только те, у кого реально есть
        // хотя бы одна попытка (остальные студенты курса тут не интересны,
        // обнулять нечего). attemptsUsed считаем тем же правилом, что и
        // Student\SubmissionController::create() — незавершённая попытка
        // в счёт не идёт.
        $attemptsByStudent = Submission::where('homework_id', $homework->id)
            ->with('user')
            ->get()
            ->filter(fn (Submission $s) => $s->user !== null)
            ->groupBy('user_id')
            ->map(function ($subs) {
                $finished = $subs->where('status', '!=', 'in_progress')->sortByDesc('id');

                return [
                    'student' => $subs->first()->user,
                    'attemptsUsed' => $finished->count(),
                    'inProgress' => $subs->contains('status', 'in_progress'),
                    'lastScore' => $finished->first()->total_score ?? null,
                ];
            })
            ->sortBy(fn (array $row) => $row['student']->name)
            ->values();

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
                ->map(function ($student) use ($unlockedUserIds, $homework) {
                    $enrolledAt = $student->courseEnrolledAt($homework->course_id);
                    // Через сам Homework::isLessonBeforeEnrollment(), а не
                    // повторную ручную формулу — иначе список молчаливо
                    // разойдётся с реальным поведением, как только у метода
                    // появляется новое исключение (см. hasSubmissionFrom()):
                    // ученик с уже сданной работой фактически не заблокирован,
                    // и не должен выглядеть здесь как "требует ручного
                    // открытия доступа".
                    $blocked = $homework->isLessonBeforeEnrollment($student);

                    return [
                        'student' => $student,
                        'enrolledAt' => $enrolledAt,
                        'blocked' => $blocked,
                        'unlocked' => in_array($student->id, $unlockedUserIds, true),
                    ];
                })
                // Показываем только тех, кого урок реально касается: либо
                // сейчас заблокирован, либо уже точечно разблокирован
                // (чтобы можно было отозвать доступ) — остальные студенты
                // курса просто не интересны на этой странице.
                ->filter(fn (array $row) => $row['blocked'] || $row['unlocked'])
                ->values();
        }

        return view('admin.homeworks.show', compact('homework', 'lateEnrollmentRows', 'attemptsByStudent'));
    }
}
