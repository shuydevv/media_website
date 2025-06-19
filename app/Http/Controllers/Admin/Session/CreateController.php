<?php

namespace App\Http\Controllers\Admin\Session;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseSession;

class CreateController extends Controller
{
    public function __invoke()
    {
        $courses = Course::with('sessions')->get();

        return view('admin.sessions.create', compact('courses'));
    }
}
