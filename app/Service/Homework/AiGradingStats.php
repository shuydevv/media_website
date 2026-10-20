<?php

namespace App\Service\Homework;

use App\Models\Submission;

/**
 * Насколько куратор согласен с черновиком ИИ — чтобы видеть, на каких
 * номерах ИИ ошибается, и уточнять правила (resources/ai/grading).
 *
 * Берутся задания, по которым куратор сохранил оценку при наличии
 * черновика ИИ (в per_task_results есть ai_score, см.
 * SubmissionReviewController::saveTask). Балл сравнивается напрямую,
 * тексты (обоснование + комментарий) — после нормализации, чтобы
 * удалённая точка или исправленная буква правкой не считались.
 */
class AiGradingStats
{
    /** Доля изменённого текста, до которой правка считается косметической. */
    public const COSMETIC = 0.15;
    /** Больше — текст переписан куратором. */
    public const REWRITTEN = 0.60;

    /**
     * @return array{
     *   total:int, score_match:int, text: array{same:int, cosmetic:int, substantial:int, rewritten:int},
     *   by_number: array<string, array{total:int, score_match:int}>
     * }
     */
    public function summary(int $days = 90): array
    {
        $out = [
            'total'       => 0,
            'score_match' => 0,
            'text'        => ['same' => 0, 'cosmetic' => 0, 'substantial' => 0, 'rewritten' => 0],
            'by_number'   => [],
        ];

        Submission::query()
            ->with('homework.tasks.task')
            ->whereNotNull('ai_drafts')
            ->where('updated_at', '>=', now()->subDays($days))
            ->chunkById(200, function ($submissions) use (&$out) {
                foreach ($submissions as $s) {
                    $drafts = $s->ai_drafts ?? [];
                    foreach ($s->per_task_results ?? [] as $key => $row) {
                        $draft = $drafts[$key] ?? null;
                        if (!is_array($row) || !isset($row['ai_score'], $row['score']) || !isset($draft['score'])) {
                            continue;
                        }

                        $match = (int) $row['ai_score'] === (int) $row['score'];
                        $out['total']++;
                        $out['score_match'] += (int) $match;

                        $change = self::changeRatio(
                            ($draft['rationale'] ?? '') . "\n" . ($draft['comment'] ?? ''),
                            ($row['reason'] ?? '') . "\n" . ($row['comment'] ?? ''),
                        );
                        $out['text'][self::textBucket($change)]++;

                        $number = (string) ($s->homework?->tasks->firstWhere('id', (int) $key)?->number ?? '—');
                        $out['by_number'][$number]['total'] = ($out['by_number'][$number]['total'] ?? 0) + 1;
                        $out['by_number'][$number]['score_match'] = ($out['by_number'][$number]['score_match'] ?? 0) + (int) $match;
                    }
                }
            });

        uksort($out['by_number'], fn ($a, $b) => (int) $a <=> (int) $b ?: strcmp($a, $b));

        return $out;
    }

    /**
     * Доля изменённого текста 0..1. Регистр, ё/е, пунктуация и пробелы не
     * учитываются: «убрал точку» — это 0.
     */
    public static function changeRatio(string $before, string $after): float
    {
        $a = self::normalize($before);
        $b = self::normalize($after);
        if ($a === $b) {
            return 0.0;
        }
        if ($a === '' || $b === '') {
            return 1.0;
        }

        // levenshtein считает по байтам — в cp1251 кириллица по байту на букву.
        $a = (string) mb_convert_encoding($a, 'CP1251', 'UTF-8');
        $b = (string) mb_convert_encoding($b, 'CP1251', 'UTF-8');

        return min(1.0, levenshtein($a, $b) / max(strlen($a), strlen($b)));
    }

    public static function textBucket(float $change): string
    {
        return match (true) {
            $change == 0.0             => 'same',
            $change <= self::COSMETIC  => 'cosmetic',
            $change <= self::REWRITTEN => 'substantial',
            default                    => 'rewritten',
        };
    }

    private static function normalize(string $text): string
    {
        $text = mb_strtolower(str_replace(['ё', 'Ё'], 'е', $text));
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);

        return trim($text);
    }
}
