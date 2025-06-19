<?php

namespace App\Http\Controllers\Admin\Course;

use App\Http\Controllers\Controller;
use App\Models\Course;

class ShowController extends Controller
{
    public function __invoke(Course $course)
    {
        $course->load(['scheduleTemplates', 'sessions']);

        return view('admin.courses.show', compact('course'));
    }
}
