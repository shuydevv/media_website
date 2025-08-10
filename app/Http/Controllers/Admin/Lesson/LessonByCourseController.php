<?php

namespace App\Http\Controllers\Admin\Lesson;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Lesson;

class LessonByCourseController extends Controller
{
    public function __invoke(Request $request)
    {
        $courseId = (int) $request->query('course_id');

        if (!$courseId) {
            return response()->json(['lessons' => []]);
        }

        // ВАЖНО: сортируем по полям из course_sessions через JOIN
        $lessons = Lesson::query()
            ->select('lessons.id', 'lessons.title', 'lessons.course_session_id')
            ->join('course_sessions', 'course_sessions.id', '=', 'lessons.course_session_id')
            ->where('course_sessions.course_id', $courseId)
            ->orderBy('course_sessions.date')
            ->orderBy('course_sessions.start_time')
            ->orderBy('lessons.title')
            ->get();

        return response()->json(['lessons' => $lessons]);
    }
}
