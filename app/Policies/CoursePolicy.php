<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    // Студент может видеть курс, если есть активная запись
    public function view(User $user, Course $course): bool
    {
        // Админов пропусти по своему правилу, если есть (пример):
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) return true;

        return $user->hasActiveEnrollment($course);
    }
}
