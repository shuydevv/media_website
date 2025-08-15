<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $user = auth()->user();

        // только активные и не истёкшие
        $courses = $user->courses()
            ->wherePivot('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            })
            ->with(['nextSession.lesson', 'category']) // 👈 добавили lesson и категорию
            ->orderBy('title')
            ->get();


        return view('student.dashboard', compact('courses'));
    }
}
