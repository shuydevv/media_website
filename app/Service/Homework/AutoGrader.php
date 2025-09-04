<?php

namespace App\Service\Homework;

use App\Models\Homework;

class AutoGrader
{
    /**
     * Старый метод — оставляем для совместимости
     */
    public function grade(Homework $homework, array $answers): array
    {
        $tasks = $homework->tasks ?? collect();
        return $this->gradeWithTasks($tasks, $answers);
    }

    /**
     * Новый метод: принимает коллекцию/массив задач.
     * Возвращает ту же структуру.
     *
     * @param iterable $tasks  коллекция/массив моделей HomeworkTask
     * @param array    $answers [task_id => answer]
     */
    public function gradeWithTasks(iterable $tasks, array $answers): array
    {
        $total = 0;
        $perTask = [];
        $hasManual = false;

        foreach ($tasks as $task) {
            $type = $task->type;
            $max  = (int)($task->max_score ?? 1);
            $correct = $task->answer ?? null;

            // ответ ученика
            $taskId = is_object($task) ? ($task->id ?? null) : ($task['id'] ?? null);
            $answer = $answers[$taskId] ?? null;

            // ручные типы
            if (in_array($type, ['written','image_written','image_manual'], true)) {
                $hasManual = true;
                $perTask[$taskId] = [
                    // 'score' => null,
                    'max'   => $max,
                    'errors'=> 0,
                    'order_matters' => false,
                    'answer' => $answer,
                    'correct'=> $correct,
                ];
                continue;
            }

            // где важен порядок
            $orderMatters = in_array($type, ['matching','table'], true);

            [$score, $errors] = $this->scoreAuto($answer, $correct, $max, $orderMatters, $type);

            $perTask[$taskId] = [
                'score' => $score,
                'max'   => $max,
                'errors'=> $errors,
                'order_matters' => $orderMatters,
                'answer' => $answer,
                'correct'=> $correct,
            ];
            $total += $score;
        }

        return [
            'fully_auto' => !$hasManual,
            'score'      => $total,
            'per_task'   => $perTask,
        ];
    }

    // ==== ниже — как у тебя было ====

    private function norm(?string $s): string
    {
        if ($s === null) return '';
        $s = preg_replace('/\s+/u', '', $s);
        $s = mb_strtolower($s);
        $s = strtr($s, ['ё' => 'е']);
        return $s ?? '';
    }

    private function scoreAuto(?string $answer, ?string $correct, int $max, bool $orderMatters, string $type): array
    {
        $a = $this->norm($answer);
        $c = $this->norm($correct);

        if ($a === '' && $c !== '') return [0, $this->errorsEmpty($c, $orderMatters)];
        if ($c === '') return [0, 0];

        if ($type === 'image_auto' && preg_match('/[a-zа-я]/u', $a)) {
            $errors = ($a === $c) ? 0 : 1;
            return [$this->scoreByErrors($errors, $max), $errors];
        }

        $arrA = preg_split('//u', $a, -1, PREG_SPLIT_NO_EMPTY);
        $arrC = preg_split('//u', $c, -1, PREG_SPLIT_NO_EMPTY);

        $errors = $orderMatters
            ? $this->countErrorsOrdered($arrA, $arrC)
            : $this->countErrorsUnordered($arrA, $arrC);

        return [$this->scoreByErrors($errors, $max), $errors];
    }

    private function errorsEmpty(string $correct, bool $orderMatters): int
    {
        return max(1, mb_strlen($correct));
    }

    private function countErrorsOrdered(array $a, array $c): int
    {
        $lenA = count($a);
        $lenC = count($c);
        $min  = min($lenA, $lenC);

        $mismatch = 0;
        for ($i = 0; $i < $min; $i++) {
            if ($a[$i] !== $c[$i]) $mismatch++;
        }
        $extra = max(0, $lenA - $lenC);
        $miss  = max(0, $lenC - $lenA);

        return $mismatch + $extra + $miss;
    }

    private function countErrorsUnordered(array $a, array $c): int
    {
        $fa = [];
        foreach ($a as $ch) $fa[$ch] = ($fa[$ch] ?? 0) + 1;
        $fc = [];
        foreach ($c as $ch) $fc[$ch] = ($fc[$ch] ?? 0) + 1;

        $keys = array_unique(array_merge(array_keys($fa), array_keys($fc)));
        $diff = 0;
        foreach ($keys as $k) {
            $diff += abs(($fa[$k] ?? 0) - ($fc[$k] ?? 0));
        }
        return (int)ceil($diff / 2);
    }

    private function scoreByErrors(int $errors, int $max): int
    {
        if ($errors <= 0) return $max;
        if ($max <= 1) return 0;
        if ($max === 2) return ($errors <= 1) ? 1 : 0;
        if ($max === 3) return max(0, 3 - $errors);
        return max(0, $max - $errors);
    }
}
