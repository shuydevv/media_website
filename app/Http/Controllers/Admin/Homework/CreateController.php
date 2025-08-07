<?php

namespace App\Http\Controllers\Admin\Homework;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;

class CreateController extends Controller
{
    public function __invoke()
    {
        $lessons = Lesson::all();  // Получаем все уроки
        $courses = Course::all();  // Получаем все уроки
        return view('admin.homeworks.create', compact('lessons', 'courses'));
    }
}

