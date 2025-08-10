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
        $courses = Course::orderBy('title')->get();

        $courseId = $request->input('course_id');
        $status   = $request->input('status'); // ← добавили
        $date     = $request->input('date');

        $sessions = CourseSession::with('course')
            ->when($courseId, fn($q) => $q->where('course_id', $courseId))
            ->when($status,   fn($q) => $q->where('status', $status)) // ← фильтр по статусу
            ->when($date,     fn($q) => $q->whereDate('date', $date))
            ->orderBy('date')
            ->orderBy('start_time')
            ->paginate(15);

        return view('admin.sessions.index', compact('courses', 'sessions'));
    }


}
