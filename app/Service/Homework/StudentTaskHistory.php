<?php

namespace App\Service\Homework;

use App\Models\Submission;

/**
 * Как студент справлялся с заданиями того же номера (HomeworkTask::number,
 * т.е. номер задания в экзамене) в прошлых проверенных попытках — чтобы
 * куратор видел не только текущий ответ, но и историю по этому типу
 * задания. Ничего похожего в проекте раньше не было (см. обсуждение перед
 * реализацией), поэтому сделано с нуля, максимально просто.
 */
class StudentTaskHistory
{
    /**
     * @return array<int, array{score:int,max:int,date:string}> новые попытки первыми, максимум $limit штук
     */
    public function forTaskNumber(int $studentId, $number, int $excludeSubmissionId, int $limit = 3): array
    {
        if ($number === null || $number === '') {
            return [];
        }

        // Лимит 20 — per_task_results/tasks у Submission живут в JSON без
        // индексов (и большая часть БД — MyISAM, см. CLAUDE.md), полное
        // сканирование всех попыток студента было бы дорогим запросом.
        $submissions = Submission::query()
            ->where('user_id', $studentId)
            ->where('id', '!=', $excludeSubmissionId)
            ->where('status', 'checked')
            ->with('homework.tasks')
            ->latest('updated_at')
            ->limit(20)
            ->get();

        $results = [];

        foreach ($submissions as $submission) {
            $homework = $submission->homework;
            if (!$homework) {
                continue;
            }

            $perTaskRes = $submission->per_task_results ?? [];

            foreach ($homework->tasks as $task) {
                if ((string) $task->number !== (string) $number) {
                    continue;
                }

                $tid = (string) ($task->id ?? $task->task_id);
                $row = $perTaskRes[$tid] ?? null;

                if (!is_array($row) || !empty($row['skipped']) || !array_key_exists('score', $row)) {
                    continue;
                }

                $results[] = [
                    'score' => (int) $row['score'],
                    'max'   => (int) ($task->max_score ?? 1),
                    'date'  => optional($submission->updated_at)->format('d.m.Y'),
                ];

                if (count($results) >= $limit) {
                    break 2;
                }
            }
        }

        return $results;
    }
}
