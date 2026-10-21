<?php

namespace App\Service\Homework;

use App\Models\HomeworkTask;
use App\Models\Submission;

/**
 * «Перерешать ошибки»: новая попытка, в которой ученик решает заново только
 * задания, где прошлая сданная попытка набрала меньше максимума. Остальные
 * переносятся как есть — ответ в answers, результат в per_task_results с
 * флагом carried. Визард пропускает перенесённые задания (они уже
 * «отвечены»), а check()/save() их не принимают — менять их в этой попытке
 * нельзя.
 *
 * Что считается ошибкой:
 *  - авто-задание — балл ниже максимума (результат автопроверки финальный);
 *  - ручное — оценка куратора ниже максимума, но только когда проверка
 *    завершена (status checked): до этого оценки в per_task_results — черновик
 *    куратора. Пустой ответ на ручное закрывается нулём ещё при сдаче — это
 *    тоже ошибка.
 * Ручное с непустым ответом, которое куратор ещё не проверил, переносится без
 * оценки и уходит на проверку уже в новой попытке (старая после сдачи новой
 * из очереди куратора пропадает — см. Submission::withoutSuperseded()).
 *
 * Пробники так не перерешиваются — это симуляция экзамена целиком.
 */
class MistakesRetry
{
    /**
     * @param  iterable<HomeworkTask>  $tasks
     * @return array{answers: array, per_task: array, redo: int[]}
     */
    public function plan(Submission $previous, iterable $tasks): array
    {
        $answers = $previous->answers ?? [];
        $perTask = $previous->per_task_results ?? [];
        $reviewDone = $previous->status === 'checked';

        $plan = ['answers' => [], 'per_task' => [], 'redo' => []];

        foreach ($tasks as $task) {
            $tid = $task->id;
            $max = (int) ($task->max_score ?? 1);
            $row = is_array($perTask[$tid] ?? null) ? $perTask[$tid] : [];
            $answer = $answers[$tid] ?? null;
            $hasAnswer = trim((string) $answer) !== '';
            $score = array_key_exists('score', $row) && $row['score'] !== null ? (int) $row['score'] : null;

            if ($task->isAutoGradable()) {
                $correct = $hasAnswer && $score !== null && $score >= $max;
            } elseif (! $hasAnswer) {
                $correct = false;
            } elseif ($reviewDone && empty($row['skipped']) && $score !== null) {
                $correct = $score >= $max;
            } else {
                // Не проверено куратором — переносим ответ без оценки.
                $plan['answers'][$tid] = $answer;
                $plan['per_task'][$tid] = ['carried' => true];

                continue;
            }

            if (! $correct) {
                $plan['redo'][] = $tid;

                continue;
            }

            $plan['answers'][$tid] = $answer;
            // Корм за это задание уже начислен в прошлой попытке — fish_awarded
            // = балл, чтобы syncTaskCorm() не начислил его повторно (дельта 0).
            $plan['per_task'][$tid] = array_merge($row, [
                'score' => $score,
                'max' => $max,
                'status' => 'ok',
                'carried' => true,
                'fish_awarded' => $score,
            ]);
        }

        return $plan;
    }
}
