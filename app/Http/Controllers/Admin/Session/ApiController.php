<?php

namespace App\Http\Controllers\Admin\Session;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseSession;
use App\Models\Lesson;
use Illuminate\Http\Request;

class ApiController extends Controller
{
    public function sessionsByCourse(Course $course)
    {
        // Получаем ID всех уже занятых сессий
        $usedSessionIds = Lesson::pluck('course_session_id')->toArray();

        // Получаем доступные сессии для данного курса
        $sessions = CourseSession::where('course_id', $course->id)
            ->whereNotIn('id', $usedSessionIds)
            ->whereDate('date', '>=', now()->toDateString()) // ← включи, если нужны только будущие
            ->orderBy('date')
            ->get(['id', 'date', 'start_time']);

        return response()->json($sessions);
    }
}
