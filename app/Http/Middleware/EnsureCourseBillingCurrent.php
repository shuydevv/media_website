<?php

namespace App\Http\Middleware;

use App\Models\Course;
use App\Service\BillingService;
use Closure;
use Illuminate\Http\Request;

class EnsureCourseBillingCurrent
{
    public function __construct(private BillingService $billing)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        $course = $this->resolveCourse($request);
        if (!$course) {
            return $next($request);
        }

        if ($user->isAdmin() || $user->isMentor()) {
            return $next($request);
        }

        // Никогда не записанного (или на разовом доступе) студента не трогаем —
        // для него штатно отработает 403 через политику, если доступа вообще нет.
        if (!$this->billing->isPastDue($user, $course)) {
            return $next($request);
        }

        if ($this->billing->hasAccess($user, $course)) {
            // Просрочено, но сейчас действует обещанный платёж — пропускаем.
            return $next($request);
        }

        return redirect()->route('billing.overdue', $course);
    }

    private function resolveCourse(Request $request): ?Course
    {
        $course = $request->route('course');
        if ($course instanceof Course) {
            return $course;
        }

        $lesson = $request->route('lesson');
        if ($lesson) {
            return $lesson->session?->course;
        }

        $homework = $request->route('homework');
        if ($homework) {
            return $homework->course;
        }

        return null;
    }
}
