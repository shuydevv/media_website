<?php

namespace App\Http\Controllers\Admin\Lesson;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\CourseSession;
use App\Models\Homework;

class EditController extends Controller
{
    public function __invoke(Lesson $lesson)
    {
        $sessions = CourseSession::with('course')->where('status', 'active')->get();
        $homeworks = Homework::all();

        return view('admin.lessons.edit', compact('lesson', 'sessions', 'homeworks'));
    }
}

