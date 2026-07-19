<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\Homework;
use App\Models\User;
use App\Service\BillingService;

class HomeworkPolicy
{
    public function view(User $user, Homework $homework): bool
    {
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) return true;

        if (!$homework->course_id) return false;

        $course = Course::find($homework->course_id);
        if (!$course) return false;

        return app(BillingService::class)->hasAccess($user, $course);
    }
}
