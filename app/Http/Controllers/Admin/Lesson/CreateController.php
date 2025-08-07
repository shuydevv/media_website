<?php

namespace App\Http\Controllers\Admin\Lesson;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseSession;
use App\Models\Homework;

class CreateController extends Controller
{
    public function __invoke()
    {
        $sessions = CourseSession::with('course')->where('status', 'active')->get();
        $homeworks = Homework::all();
        $courses = Course::all();

        return view('admin.lessons.create', compact('sessions', 'homeworks', 'courses'));
    }
}

