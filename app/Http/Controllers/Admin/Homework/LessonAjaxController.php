<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use Illuminate\Http\Request;

class LessonAjaxController extends Controller
{
    public function getLessons(Request $request)
    {
        // Получаем все уроки для выбранного курса
        $lessons = Lesson::where('course_id', $request->course_id)->get();

        // Возвращаем уроки в формате JSON для использования на фронте
        return response()->json(['lessons' => $lessons]);
    }
}
