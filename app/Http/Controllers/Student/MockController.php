<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Homework;
use App\Models\Submission;
use App\Service\BillingService;

class MockController extends Controller
{
    /**
     * Список пробников студента — отдельная страница от обычных домашек
     * (см. HomeworkController::index(), там type=mock намеренно исключён).
     * Один пробник = одна карточка (не по попытке): при пересдаче карточка
     * показывает последнюю финализированную попытку.
     */
    public function index(BillingService $billing)
    {
        $user = auth()->user();

        $courses = $user->courses()->wherePivot('status', 'active')->get();

        $accessibleCourseIds = $courses
            ->filter(fn ($course) => $billing->hasAccess($user, $course))
            ->pluck('id')
            ->all();

        $mocks = Homework::query()
            ->whereIn('course_id', $accessibleCourseIds)
            ->where('type', 'mock')
            ->with(['course', 'tasks', 'lesson.courseSession'])
            ->get()
            ->reject(fn (Homework $hw) => $hw->isLessonUpcoming())
            ->values();

        $mockIds = $mocks->pluck('id');

        $latestFinishedByHomework = Submission::query()
            ->where('user_id', $user->id)
            ->whereIn('homework_id', $mockIds)
            ->where('status', '!=', 'in_progress')
            ->orderByDesc('id')
            ->get()
            ->groupBy('homework_id')
            ->map(fn ($group) => $group->first());

        $inProgressByHomework = Submission::query()
            ->where('user_id', $user->id)
            ->whereIn('homework_id', $mockIds)
            ->where('status', 'in_progress')
            ->get()
            ->keyBy('homework_id');

        $attemptsUsedByHomework = Submission::query()
            ->where('user_id', $user->id)
            ->whereIn('homework_id', $mockIds)
            ->where('status', '!=', 'in_progress')
            ->get()
            ->countBy('homework_id');

        $rows = $mocks->map(function (Homework $hw) use ($latestFinishedByHomework, $inProgressByHomework, $attemptsUsedByHomework) {
            $submission = $latestFinishedByHomework->get($hw->id);
            $inProgress = $inProgressByHomework->get($hw->id);

            if ($inProgress) {
                $status = 'in_progress';
            } elseif (!$submission) {
                $status = 'not_started';
            } elseif ($submission->status === 'checked') {
                $status = 'checked';
            } else {
                // 'pending'/'expired' — как и на общем списке домашек, это ещё «на проверке».
                $status = 'pending_review';
            }

            $primaryMax = (int) $hw->tasks->sum('max_score');
            $primaryScore = $submission?->total_score;
            $scaledScore = ($submission !== null && $primaryMax > 0 && $primaryScore !== null)
                ? (int) round($primaryScore / $primaryMax * 100)
                : null;

            $attemptsUsed = (int) ($attemptsUsedByHomework->get($hw->id) ?? 0);
            $attemptsLeft = max(0, $hw->attemptsAllowed() - $attemptsUsed);

            return [
                'homework'     => $hw,
                'courseTitle'  => $hw->course->title ?? 'Курс',
                'mockNumber'   => $hw->mock_number,
                'status'       => $status,
                'submission'   => $submission,
                'primaryScore' => $primaryScore,
                'primaryMax'   => $primaryMax,
                'scaledScore'  => $scaledScore,
                'submittedAt'  => $submission?->updated_at,
                'attemptsLeft' => $attemptsLeft,
            ];
        });

        // Стабильная сортировка в два прохода — сперва по номеру пробника,
        // затем по статусу (тот же приём, что в HomeworkController::index()):
        // внутри одной группы статуса порядок по номеру сохраняется.
        $rows = $rows->sortBy(fn ($row) => $row['mockNumber'] ?? PHP_INT_MAX)->values();

        $priority = ['in_progress' => 0, 'not_started' => 1, 'pending_review' => 2, 'checked' => 3];
        $rows = $rows->sortBy(fn ($row) => $priority[$row['status']])->values();

        return view('student.mocks.index', ['rows' => $rows]);
    }
}
