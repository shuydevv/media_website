<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\User;

class EditController extends Controller
{
    public function __invoke(User $user) {
        $roles = User::getRoles();
        $courses = Course::orderBy('title')->get(['id', 'title']);

        // course_id => текущая дата доступа (для предзаполнения) — та же
        // развилка по billing_interval_days, что и в /admin/crm.
        $enrolledUntil = $user->courses->mapWithKeys(function ($course) {
            $pivot = $course->pivot;
            $raw = $pivot->billing_interval_days === null ? $pivot->expires_at : $pivot->next_payment_due_at;
            return [$course->id => $raw ? \Illuminate\Support\Carbon::parse($raw)->format('Y-m-d') : null];
        });

        return view('admin.users.edit', compact('user', 'roles', 'courses', 'enrolledUntil'));
    }
}
