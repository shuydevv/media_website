<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Submission;

class LessonController extends Controller
{
    public function show(Lesson $lesson)
    {
        // Проверяем доступ по курсу через политику CoursePolicy
        $course = $lesson->courseSession->course ?? null;
        abort_unless($course, 404);
        $this->authorize('view', $course);

        $homework = $lesson->homework ?? null;
        $mySubmission = null;
        $homeworkUpcoming = false;

        if ($homework) {
            // $lesson уже загружен (вместе с courseSession — см. abort_unless
            // выше), переиспользуем его вместо того, чтобы isLessonUpcoming()
            // сама лениво дозагружала homework->lesson->courseSession.
            $homework->setRelation('lesson', $lesson);
            $homeworkUpcoming = $homework->isLessonUpcoming();

            if (auth()->check()) {
                // Только ЗАВЕРШЁННая попытка — повод показать "Смотреть
                // результаты". Незаконченная (in_progress) — это не
                // результат, туда нечего смотреть; кнопка "Перейти к
                // домашке" должна остаться и сама привести на продолжение
                // (см. SubmissionController::create(), она это уже умеет).
                $mySubmission = Submission::where('homework_id', $homework->id)
                    ->where('user_id', auth()->id())
                    ->where('status', '!=', 'in_progress')
                    ->latest('id')
                    ->first();
            }
        }

        // Здесь можно будет добавить материалы/домашку, сейчас — безопасный минимум
        return view('student.lessons.show', [
            'lesson' => $lesson,
            'course' => $course,
            'mySubmission' => $mySubmission,
            'homeworkUpcoming' => $homeworkUpcoming,
        ]);
    }
}
