<?php

namespace App\Policies;

use App\Models\Homework;
use App\Models\User;

class HomeworkPolicy
{
    public function view(User $user, Homework $homework): bool
    {
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) return true;

        if (!$homework->course_id) return false;
        return $user->hasActiveEnrollment($homework->course_id);
    }
}
