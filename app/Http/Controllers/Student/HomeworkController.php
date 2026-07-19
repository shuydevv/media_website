<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Homework;
use App\Models\HomeworkTask;
use App\Models\Submission;
use App\Service\BillingService;

class HomeworkController extends Controller
{
    /**
     * Список всех домашек студента по всем курсам сразу (не только топ-3,
     * как на дашборде). Пробники (type=mock) сюда не попадают — у них
     * будет отдельная страница/вкладка, чтобы не задваивать одно и то же
     * в двух местах.
     */
    public function index(BillingService $billing)
    {
        $user = auth()->user();

        $courses = $user->courses()->wherePivot('status', 'active')->get();

        // Курс может быть оплачен, но сейчас просрочен (доступ приостановлен) —
        // домашки таких курсов не показываем, тот же принцип, что и на
        // дашборде (см. DashboardController), иначе с этой страницы будет
        // видно содержимое курса, к которому формально доступ закрыт.
        $accessibleCourseIds = $courses
            ->filter(fn ($course) => $billing->hasAccess($user, $course))
            ->pluck('id')
            ->all();

        $homeworks = Homework::query()
            ->whereIn('course_id', $accessibleCourseIds)
            ->where('type', 'homework')
            ->with(['course', 'tasks', 'lesson.courseSession'])
            ->orderBy('due_at')
            ->get()
            // Пока урок, к которому привязана домашка, ещё не наступил, ученик
            // вообще не должен знать о её существовании (см. Homework::isLessonUpcoming()).
            ->reject(fn (Homework $hw) => $hw->isLessonUpcoming())
            ->values();

        $latestSubmissionByHomework = Submission::query()
            ->where('user_id', $user->id)
            ->whereIn('homework_id', $homeworks->pluck('id'))
            ->orderByDesc('id')
            ->get()
            ->groupBy('homework_id')
            ->map(fn ($group) => $group->first());

        $rows = $homeworks->map(function (Homework $hw) use ($latestSubmissionByHomework) {
            $submission = $latestSubmissionByHomework->get($hw->id);
            $isPastDue = $hw->due_at !== null && now()->isAfter($hw->due_at);

            if (!$submission) {
                $status = $isPastDue ? 'overdue' : 'not_started';
            } elseif ($submission->status === 'in_progress') {
                $status = 'in_progress';
            } elseif ($submission->status === 'checked') {
                $status = 'checked';
            } elseif ($submission->status === 'expired') {
                // 'expired' — сдано ПОСЛЕ дедлайна, но именно сдано, не брошено:
                // finishSubmit() сначала считает checked/pending по итогам
                // проверки, а потом затирает это на 'expired', если срок вышел —
                // сам факт "было ли это авто-проверено" в status уже не виден.
                // Пересчитываем по тем же правилам, что и контроллер сдачи, и
                // кладём в ту же категорию, что и вовремя сданные.
                $hasManualTasks = $hw->tasks->contains(
                    fn ($t) => in_array($t->type, HomeworkTask::MANUAL_TYPES, true)
                );
                $status = $hasManualTasks ? 'pending_review' : 'checked';
            } else {
                $status = 'pending_review'; // 'pending' и любые легаси-значения
            }

            return [
                'homework' => $hw,
                'course_title' => $hw->course->title ?? 'Курс',
                'submission' => $submission,
                'status' => $status,
                'score' => $submission?->total_score,
                'max_score' => $hw->tasks->sum('max_score'),
            ];
        });

        // Сортировка: "в процессе" — первым делом (начатое стоит доделать),
        // затем просроченное и не начатое, на проверке — дальше, готовое — в конец.
        $priority = ['in_progress' => 0, 'overdue' => 1, 'not_started' => 2, 'pending_review' => 3, 'checked' => 4];
        $rows = $rows->sortBy(fn ($row) => $priority[$row['status']])->values();

        return view('student.homeworks.index', ['rows' => $rows]);
    }
}
