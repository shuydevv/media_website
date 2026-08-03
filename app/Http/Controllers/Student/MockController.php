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

            $attemptsUsed = (int) ($attemptsUsedByHomework->get($hw->id) ?? 0);
            $attemptsLeft = max(0, $hw->attemptsAllowed() - $attemptsUsed);

            // Реальные баллы по секциям — та же логика, что и на странице
            // результата (submissions/show.blade.php: $autoScore/$manualScore/
            // $autoPct/$manualPct), а не "отвечено/не отвечено": иначе
            // полностью сданная, но плохо решённая работа показывала бы
            // 100%, что и есть тот самый "нереальный" процент.
            $progressSubmission = $inProgress ?? $submission;
            $perTaskResults = $progressSubmission?->per_task_results ?? [];

            $autoTasks = $hw->tasks->filter(fn ($t) => $t->isAutoGradable());
            $manualTasks = $hw->tasks->filter(fn ($t) => !$t->isAutoGradable());

            $autoMax = (int) $autoTasks->sum('max_score');
            $manualMax = (int) $manualTasks->sum('max_score');

            $autoScore = $progressSubmission?->autocheck_score !== null
                ? (int) $progressSubmission->autocheck_score
                : (int) $autoTasks->sum(fn ($t) => (int) ($perTaskResults[$t->id]['score'] ?? 0));

            $manualScore = ($progressSubmission?->status === 'checked' && $progressSubmission->manual_score !== null)
                ? (int) $progressSubmission->manual_score
                : (int) $manualTasks->sum(function ($t) use ($perTaskResults) {
                    $row = $perTaskResults[$t->id] ?? [];
                    $hasScore = array_key_exists('score', $row) && $row['score'] !== null;

                    return (!($row['skipped'] ?? false) && $hasScore) ? (int) $row['score'] : 0;
                });

            $pct = fn (int $score, int $max) => $max > 0 ? (int) round(min(100, max(0, $score * 100 / $max))) : 0;

            $totalMax = $autoMax + $manualMax;
            $totalScore = ($progressSubmission?->status === 'checked' && $progressSubmission->total_score !== null)
                ? (int) $progressSubmission->total_score
                : $autoScore + $manualScore;

            // Число рядом с "Первая/Вторая часть" — вклад части в общий
            // стобалльный балл (пример: 34 из 58 первичных = 59; из них 12
            // за автопроверку и 22 за ручную → 21 и 38, сумма = 59 = центр
            // диаграммы).
            $autoScaled = $pct($autoScore, $totalMax);
            $manualScaled = $pct($manualScore, $totalMax);
            $scaledScore = $pct($totalScore, $totalMax);

            // А вот ЗАЛИВКА самого кольца — это отдельная величина: % от
            // СОБСТВЕННОГО максимума части (12/28=43%, 22/30=73%), а не от
            // общего. Если залить кольцо по autoScaled/manualScaled, то даже
            // при 100%-но верно решённой части кольцо никогда не дойдёт до
            // конца (оно упирается в долю части от общего максимума) — это
            // и есть "график думает, что 21 балл = 21%", хотя 21 — это доля
            // от ОБЩЕГО балла, а не % выполнения самой части.
            $autoPct = $pct($autoScore, $autoMax);
            $manualPct = $pct($manualScore, $manualMax);

            return [
                'homework'     => $hw,
                'courseTitle'  => $hw->course->title ?? 'Курс',
                'mockNumber'   => $hw->mock_number,
                'status'       => $status,
                'submission'   => $submission,
                'submittedAt'  => $submission?->updated_at,
                'attemptsLeft' => $attemptsLeft,
                'autoScaled'   => $autoScaled,
                'manualScaled' => $manualScaled,
                'autoPct'      => $autoPct,
                'manualPct'    => $manualPct,
                'scaledScore'  => $scaledScore,
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
