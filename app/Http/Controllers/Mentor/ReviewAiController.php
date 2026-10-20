<?php

namespace App\Http\Controllers\Mentor;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use App\Models\User;
use App\Service\Homework\ClaudeGrader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReviewAiController extends Controller
{
    public function regen(Request $request, Submission $submission, string $taskId, ClaudeGrader $grader)
    {
        // Единственный метод в mentor/review, который раньше вообще не
        // проверял права внутри себя — полагался только на route middleware
        // ['auth','mentor'], в отличие от saveTask/skipTask/unskipTask/finish
        // в SubmissionReviewController (assertMentorOrAdmin() + authorize('update')).
        // Без этого куратор B мог генерировать ИИ-черновик по работе, которую
        // прямо сейчас держит залоченной куратор A (SubmissionPolicy::update
        // учитывает locked_by, роль admin проходит всегда).
        $u = $request->user();
        abort_unless(
            $u && in_array((int) $u->role, [User::ROLE_MENTOR, User::ROLE_ADMIN], true),
            403,
            'Доступ только для куратора или администратора.'
        );
        $this->authorize('update', $submission);

        if (!$grader->isEnabled()) {
            return response()->json(['ok' => false, 'message' => 'ИИ-проверка не настроена: нет ANTHROPIC_API_KEY.'], 503);
        }

        $task = $submission->homework->tasks()->with('task.category')->find($taskId);
        if (!$task) {
            return response()->json(['ok' => false, 'message' => 'Задание не найдено в этой домашке.'], 404);
        }

        try {
            $draft = $grader->grade($grader->payloadFor($submission, $task));
        } catch (\Throwable $e) {
            Log::error('ClaudeGrader failed', ['submission' => $submission->id, 'task' => $taskId, 'error' => $e->getMessage()]);
            return response()->json(['ok' => false, 'message' => 'Не удалось получить черновик от ИИ: ' . $e->getMessage()], 502);
        }

        $drafts = $submission->ai_drafts ?? [];
        $drafts[$taskId] = $draft;
        $submission->ai_drafts = $drafts;
        $submission->save();

        return response()->json(['ok' => true, 'taskId' => $taskId] + $draft);
    }
}
