<?php

namespace App\Http\Controllers\Admin\Session;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseSession;
use Illuminate\Http\Request;

class IndexController extends Controller
{
    public function __invoke(Request $request)
    {
        // Загружаем курсы для выпадающего списка фильтра
        $courses = Course::orderBy('title')->get();

        // Получаем id курса из запроса
        $courseId = $request->input('course_id');

        // Загружаем занятия с фильтрацией и пагинацией
        $sessions = CourseSession::with('course')
            ->when($courseId, function ($query, $courseId) {
                $query->where('course_id', $courseId);
            })
            ->orderBy('date')
            ->orderBy('start_time')
            ->paginate(15);

        return view('admin.sessions.index', compact('courses', 'sessions'));
    }
}
