<?php

namespace App\Http\Controllers\Admin\Homework;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Task;

class CreateController extends Controller
{
    public function __invoke()
    {
        $courses = Course::with('category')->orderBy('title')->get();

        // На старте можно ничего не подгружать, пока курс не выбран
        $tasks = collect();
        $lessons = collect();
        if ($courses->isNotEmpty()) {
            $firstCourseId = $courses->first()->id;
            $lessons = Lesson::whereHas('session', fn($q) => $q->where('course_id', $firstCourseId))
                ->orderBy('title')
                ->get(['id','title']);
        }

        if ($courses->isNotEmpty()) {
            $firstCourse = $courses->first();
            $categoryId  = $firstCourse->category_id; // у тебя есть relation category()
            $tasks = Task::where('category_id', $categoryId)->orderBy('number')->get(['id','number']);
        }

        return view('admin.homeworks.create', compact('lessons', 'courses', 'tasks'));
    }
}
