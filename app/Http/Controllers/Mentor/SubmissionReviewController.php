<?php

namespace App\Http\Controllers\Mentor;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use Illuminate\Http\Request;

class SubmissionReviewController extends Controller
{
    // Показ попытки с разбивкой на авто/ручные задачи
    public function show(Submission $submission)
    {
        $submission->loadMissing('homework.lesson.courseSession.course');

        $homework = $submission->homework;
        $tasksRaw = $homework->tasks ?? [];
        $tasksCol = collect($tasksRaw)->map(fn($t) => is_array($t) ? (object)$t : $t);

        $manualTypes = ['written','image_written','image_manual'];

        $autoTasks   = $tasksCol->filter(fn($t) => !in_array($t->type ?? '', $manualTypes, true))->values();
        $manualTasks = $tasksCol->filter(fn($t) =>  in_array($t->type ?? '', $manualTypes, true))->values();

        return view('mentor.submissions.show', [
            'submission'  => $submission,
            'homework'    => $homework,
            'autoTasks'   => $autoTasks,
            'manualTasks' => $manualTasks,
        ]);
    }

    // Выставление баллов/комментария по одной РУЧНОЙ задаче
    public function scoreTask(Request $request, Submission $submission, $taskId)
    {
        $homework = $submission->homework;

        // ищем нужную задачу по id среди задач домашки
        $task = collect($homework->tasks ?? [])
            ->map(fn($t) => is_array($t) ? (object)$t : $t)
            ->first(fn($t) => (string)($t->id ?? '') === (string)$taskId);

        if (!$task) {
            return back()->withErrors(['task' => 'Задача не найдена.']);
        }

        $max = (int)($task->max_score ?? 1);

        $data = $request->validate([
            'score'    => ['required','integer','min:0','max:'.$max],
            'comment'  => ['nullable','string'],
        ]);

        $ptr = $submission->per_task_results ?? [];
        if (!isset($ptr[$taskId]) || !is_array($ptr[$taskId])) {
            $ptr[$taskId] = [];
        }
        $ptr[$taskId]['score']   = (int)$data['score'];
        $ptr[$taskId]['comment'] = $data['comment'] ?? null;
        $ptr[$taskId]['checked_by'] = $request->user()->id ?? null;
        $ptr[$taskId]['checked_at'] = now()->toDateTimeString();

        // пересчитываем ручную часть и итог
        $manualTypes = ['written','image_written','image_manual'];
        $tasksCol = collect($homework->tasks ?? [])->map(fn($t) => is_array($t) ? (object)$t : $t);
        $manualTasks = $tasksCol->filter(fn($t) => in_array($t->type ?? '', $manualTypes, true))->values();

        $manualScore = 0;
        foreach ($manualTasks as $t) {
            $tid = $t->id ?? null;
            if (!$tid) continue;
            $manualScore += (int)($ptr[$tid]['score'] ?? 0);
        }

        $submission->per_task_results = $ptr;
        $submission->manual_score     = $manualScore;
        // total = автопроверка + ручная (если автопроверка уже посчитана)
        $submission->total_score      = (int)($submission->autocheck_score ?? 0) + (int)$manualScore;
        // не финализируем статус здесь, он меняется в finalize()
        $submission->save();

        return back()->with('success', 'Оценка по задаче сохранена.');
    }

    // Финализация попытки — фиксирует статус и итог
    public function finalize(Request $request, Submission $submission)
    {
        $homework = $submission->homework;

        // На всякий случай ещё раз пересчитаем ручную часть по сохранённым per_task_results
        $ptr = $submission->per_task_results ?? [];

        $manualTypes = ['written','image_written','image_manual'];
        $tasksCol = collect($homework->tasks ?? [])->map(fn($t) => is_array($t) ? (object)$t : $t);
        $manualTasks = $tasksCol->filter(fn($t) => in_array($t->type ?? '', $manualTypes, true))->values();

        $manualScore = 0;
        foreach ($manualTasks as $t) {
            $tid = $t->id ?? null;
            if (!$tid) continue;
            $manualScore += (int)($ptr[$tid]['score'] ?? 0);
        }

        $submission->manual_score = $manualScore;
        $submission->total_score  = (int)($submission->autocheck_score ?? 0) + (int)$manualScore;
        $submission->status       = 'checked'; // финальный статус
        $submission->save();

        return back()->with('success', 'Проверка попытки завершена.');
    }
}
