<?php

namespace App\Http\Controllers\Admin\Announcement;

use App\Http\Controllers\Controller;
use App\Models\Course;

class CreateController extends Controller
{
    public function __invoke()
    {
        $courses = Course::orderBy('title')->get();

        return view('admin.announcements.create', compact('courses'));
    }
}
