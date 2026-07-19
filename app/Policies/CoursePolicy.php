<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;
use App\Service\BillingService;

class CoursePolicy
{
    // Студент может видеть курс, если есть активная запись и оплата не просрочена
    public function view(User $user, Course $course): bool
    {
        if ($user->isAdmin() || $user->isMentor()) {
            return true;
        }

        return app(BillingService::class)->hasAccess($user, $course);
    }
}
