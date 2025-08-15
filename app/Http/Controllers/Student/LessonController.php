<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Lesson;

class LessonController extends Controller
{
    public function show(Lesson $lesson)
    {
        // Проверяем доступ по курсу через политику CoursePolicy
        $course = $lesson->courseSession->course ?? null;
        abort_unless($course, 404);
        $this->authorize('view', $course);

        // Здесь можно будет добавить материалы/домашку, сейчас — безопасный минимум
        return view('student.lessons.show', [
            'lesson' => $lesson,
            'course' => $course,
        ]);
    }
}
