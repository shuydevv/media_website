<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;

class CourseTaskController extends Controller
{
    public function index(Course $course)
    {
        // если у курса нет категории — возвращаем пусто
        if (!$course->category_id || !$course->category) {
            return response()->json([], 200);
        }

        $tasks = $course->category
            ->tasks()               // ВАЖНО: нужна связь tasks() в Category
            ->select('id', 'number')
            ->orderBy('number')
            ->get();

        return response()->json($tasks, 200);
    }
}
