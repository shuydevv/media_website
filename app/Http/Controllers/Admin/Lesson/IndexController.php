<?php

namespace App\Http\Controllers\Admin\Lesson;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Course;
use Illuminate\Http\Request;

class IndexController extends Controller
{
    public function __invoke(Request $request)
    {
        // Получаем все курсы для фильтра
        $courses = Course::all();

        // Фильтрация по course_id (если передан)
        $query = Lesson::with(['session.course']);

        if ($request->filled('course_id')) {
            $query->whereHas('session', function ($q) use ($request) {
                $q->where('course_id', $request->course_id);
            });
        }

        // Пагинация
        $lessons = $query->orderByDesc('id')->paginate(20);

        return view('admin.lessons.index', compact('lessons', 'courses'));
    }
}

