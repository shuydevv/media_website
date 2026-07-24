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
            ->select('id', 'number', 'type', 'question_text')
            ->orderBy('number')
            ->get()
            // Короткий превью вопроса — чтобы в поиске конструктора домашки
            // было видно не только номер, а что это вообще за задание.
            ->map(fn ($t) => [
                'id'      => $t->id,
                'number'  => $t->number,
                'type'    => $t->type,
                'preview' => $t->question_text ? \Illuminate\Support\Str::limit(strip_tags($t->question_text), 60) : null,
            ]);

        return response()->json($tasks, 200);
    }
}
