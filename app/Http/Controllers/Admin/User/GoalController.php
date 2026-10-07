<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseUser;
use App\Models\User;
use Illuminate\Http\Request;

class GoalController extends Controller
{
    /**
     * Цель ученика и входной результат по курсу (в тестовых баллах ЕГЭ,
     * 0–100) — с чем сравнивать его текущие проценты в отчёте.
     */
    public function __invoke(Request $request, User $user, Course $course)
    {
        $data = $request->validate([
            'target_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'entry_score' => ['nullable', 'integer', 'min:0', 'max:100'],
        ], [], [
            'target_score' => 'Цель',
            'entry_score' => 'Входной результат',
        ]);

        $pivot = CourseUser::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->firstOrFail();

        $pivot->target_score = $data['target_score'] ?? null;
        $pivot->entry_score = $data['entry_score'] ?? null;
        $pivot->save();

        return back()->with('success', 'Цель сохранена.');
    }
}
