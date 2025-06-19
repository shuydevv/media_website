<?php

namespace App\Http\Controllers\Admin\Course;

use App\Http\Controllers\Controller;
use App\Models\Course;

class IndexController extends Controller
{
    public function __invoke() {
        $courses = Course::all();

        return view('admin.courses.index', compact('courses'));
    }
}
