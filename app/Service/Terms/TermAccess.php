<?php

namespace App\Service\Terms;

use App\Models\User;
use App\Service\BillingService;
use Illuminate\Support\Facades\DB;

/**
 * Кому открыт тренажёр терминов. Достаточно одного из условий:
 *  - активный доступ к курсу обществознания (категория курса определяется
 *    по названию — отдельного признака предмета у курса нет);
 *  - активный доступ к курсу, который админ привязал к тренажёру
 *    (term_trainer_courses);
 *  - личная выдача админом (term_trainer_users) — курсы не нужны.
 * Админ и куратор видят тренажёр всегда.
 */
class TermAccess
{
    public function __construct(private BillingService $billing)
    {
    }

    public function allows(User $user): bool
    {
        if ($user->isAdmin() || $user->isMentor()) {
            return true;
        }

        if (DB::table('term_trainer_users')->where('user_id', $user->id)->exists()) {
            return true;
        }

        $linked = DB::table('term_trainer_courses')->pluck('course_id')->flip();
        $keyword = mb_strtolower((string) config('terms.subject_keyword'));

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
