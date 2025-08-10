<?php

namespace App\Policies;

use App\Models\Lesson;
use App\Models\User;

class LessonPolicy
{
    public function view(User $user, Lesson $lesson): bool
    {
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) return true;

        $session = $lesson->session;             // belongsTo CourseSession
        $course  = $session?->course;            // belongsTo Course
        if (!$course) return false;

        return $user->hasActiveEnrollment($course->id);
    }
}
