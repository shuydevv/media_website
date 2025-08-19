<?php

namespace App\Service;

use App\Models\Homework;
use App\Models\Submission;

class ScoreService
{
    public function check(Submission $submission): array
    {
        $results = [];
        $total = 0;

        foreach ($submission->homework->tasks as $task) {
            $answer = $submission->answers[$task->id] ?? null;
            $correct = $task->answer;
            $max = (int)$task->max_score;

            [$score, $errors] = $this->scoreTask($answer, $correct, $max);

            $results[$task->id] = [
                'answer' => $answer,
                'correct' => $correct,
                'score' => $score,
                'max' => $max,
                'errors' => $errors,
            ];
            $total += $score;
        }

        $submission->autocheck_score = $total;
        $submission->total_score = $total; // пока без ручной проверки
        $submission->save();

        return $results;
    }

    private function scoreTask(?string $answer, ?string $correct, int $max): array
    {
        if ($answer === null || $correct === null) {
            return [0, ['no answer']];
        }

        // нормализация: цифры/буквы без пробелов, нижний регистр, е/ё = е
        $norm = fn($s) => strtr(mb_strtolower(preg_replace('/\s+/u', '', $s)), ['ё'=>'е']);

        $a = str_split($norm($answer));
        $c = str_split($norm($correct));

        if ($a === $c) {
            return [$max, []]; // идеально
        }

        $errors = $this->countErrors($a, $c);

        if ($max === 1) {
            return [0, $errors];
        }
        if ($max === 2) {
            return [($errors <= 1 ? 1 : 0), $errors];
        }
        if ($max === 3) {
            $score = max(0, $max - $errors);
            return [$score, $errors];
        }
        // Сейчас программа поддерживает до 3 баллов за задание первой части, т.к. самое дорогое задания это таблица в ЕГЭ по истории (задание 4). Если появится задание в первой части на 4 балла, программу придется доработать

        // fallback
        return [0, $errors];
    }

    private function countErrors(array $a, array $c): int
    {
        $errors = 0;

        // Лишние символы
        foreach ($a as $ch) {
            if (!in_array($ch, $c)) $errors++;
        }

        // Недостающие символы
        foreach ($c as $ch) {
            if (!in_array($ch, $a)) $errors++;
        }

        // Если длина совпала, но есть несовпадения
        if (count($a) === count($c) && $a !== $c) {
            $errors++;
        }

        return $errors;
    }
}
