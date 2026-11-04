<?php

namespace App\Service\Dates;

use App\Models\User;
use App\Service\BillingService;
use Illuminate\Support\Facades\DB;

/**
 * Кому открыт тренажёр дат. Те же правила, что у тренажёра терминов
 * (App\Service\Terms\TermAccess), только предмет — история:
 *  - активный доступ к курсу истории (по названию категории курса);
 *  - активный доступ к курсу из hist_trainer_courses;
 *  - личная выдача (hist_trainer_users).
 * Админ и куратор видят тренажёр всегда.
 */
class HistAccess
{
    public function __construct(private BillingService $billing)
    {
    }

    public function allows(User $user): bool
    {
        if ($user->isAdmin() || $user->isMentor()) {
            return true;
        }

        if (DB::table('hist_trainer_users')->where('user_id', $user->id)->exists()) {
            return true;
        }

        $linked = DB::table('hist_trainer_courses')->pluck('course_id')->flip();
        $keyword = mb_strtolower((string) config('dates.subject_keyword'));

        foreach ($user->courses()->with('category')->get() as $course) {
            $isSubject = $keyword !== ''
                && $course->category
                && str_contains(mb_strtolower($course->category->title), $keyword);

            if (($isSubject || $linked->has($course->id)) && $this->billing->hasAccess($user, $course)) {
                return true;
            }
        }

        return false;
    }
}
