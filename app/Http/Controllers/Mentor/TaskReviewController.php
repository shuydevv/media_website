<?php

namespace App\Http\Controllers\Mentor;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskReview;
use App\Service\Bank\WrittenPractice;
use Illuminate\Http\Request;

/**
 * Проверка ответов второй части, отправленных со станции «Задания».
 * Очередь — блок «Задания из тренажёра» на /mentor/submissions; здесь —
 * экран одного ответа: условие, ответ ученика, образец, критерии номера,
 * баллы и комментарий. Открыл — ответ закреплён за куратором на 30 минут
 * (как у домашек), чтобы двое не проверяли одно и то же.
 */
class TaskReviewController extends Controller
{
    public function __construct(private WrittenPractice $written)
    {
    }

    public function show(Request $request, TaskReview $taskReview)
    {
        abort_if($taskReview->status === TaskReview::DRAFT || $taskReview->status === TaskReview::SELF, 404);
        $mentor = $request->user();
        $lockedByOther = $taskReview->status === TaskReview::SENT && ! $this->written->lock($taskReview, $mentor);
        $tasks = $taskReview->tasks();

        return view('mentor.task-reviews.show', [
            'review' => $taskReview->load(['user', 'checker']),
            'tasks' => $tasks,
            'criteria' => $tasks->mapWithKeys(fn (Task $t) => [$t->id => $t->resolved_criteria]),
            'lockedByOther' => $lockedByOther,
            'numberTitles' => config('task_trainer.numbers'),
        ]);
    }

    public function update(Request $request, TaskReview $taskReview)
    {
        $data = $request->validate([
            'scores' => ['required', 'array'],
            'scores.*' => ['integer', 'min:0', 'max:6'],
            'comment' => ['nullable', 'string', 'max:5000'],
        ]);
        $this->written->grade($taskReview, $request->user(), $data['scores'], $data['comment'] ?? null);

        return redirect()->route('mentor.submissions.index')->with('success', 'Ответ проверен');
    }

    public function unlock(Request $request, TaskReview $taskReview)
    {
        $this->written->unlock($taskReview, $request->user());

        return redirect()->route('mentor.submissions.index');
    }
}
