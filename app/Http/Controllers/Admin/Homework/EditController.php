<?php

namespace App\Http\Controllers\Admin\Homework;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Homework;

class EditController extends Controller
{
public function __invoke(Homework $homework)
{
    $homework->load(['tasks' => function ($q) {
        $q->orderBy('order')->orderBy('id');
    }]);

    $courses = Course::orderBy('title')->get();

    $lessons = Lesson::query()
        ->select('lessons.id', 'lessons.title')
        ->join('course_sessions', 'course_sessions.id', '=', 'lessons.course_session_id')
        ->where('course_sessions.course_id', $homework->course_id)
        ->orderBy('course_sessions.date')
        ->orderBy('course_sessions.start_time')
        ->orderBy('lessons.title')
        ->get();

    return view('admin.homeworks.edit', compact('homework', 'courses', 'lessons'));
}

}
