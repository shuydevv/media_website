<?php

namespace App\Http\Controllers\Admin\Homework;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;

class CreateController extends Controller
{
    public function __invoke()
    {
        $courses = Course::orderBy('title')->get();

        $lessons = collect();
        if ($courses->isNotEmpty()) {
            $firstCourseId = $courses->first()->id;
            $lessons = Lesson::whereHas('session', fn($q) => $q->where('course_id', $firstCourseId))
                ->orderBy('title')
                ->get(['id','title']);
        }

        return view('admin.homeworks.create', compact('lessons', 'courses'));
    }
}
