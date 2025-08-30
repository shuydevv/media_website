<?php


namespace App\Http\Controllers\Admin\Course;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Course;

class EditController extends Controller
{
    public function __invoke(Course $course)
    {
        $course->load('scheduleTemplates');
        $categories = Category::all();
        return view('admin.courses.edit', compact('course', 'categories'));
    }
}