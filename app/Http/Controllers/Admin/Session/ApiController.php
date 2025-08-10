<?php

namespace App\Http\Controllers\Admin\Session;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseSession;
use App\Models\Lesson;
use Illuminate\Http\Request;

class ApiController extends Controller
{
public function sessionsByCourse(Request $request, Course $course)
{
    // ID сессий, занятых уроками (чтобы скрыть уже использованные)
    $usedSessionIds = Lesson::pluck('course_session_id')->toArray();

    $q = CourseSession::where('course_id', $course->id)
        ->whereNotIn('id', $usedSessionIds)
        ->orderBy('date')
        ->orderBy('start_time');

    // Если передали дату: фильтруем именно этот день
    if ($request->filled('date')) {
        $q->whereDate('date', $request->input('date'));
    } else {
        // иначе показываем только будущие/сегодняшние
        $q->whereDate('date', '>=', now()->toDateString());
    }

    $sessions = $q->get(['id', 'date', 'start_time', 'end_time', 'status', 'duration_minutes']);

    return response()->json($sessions);
}

}
