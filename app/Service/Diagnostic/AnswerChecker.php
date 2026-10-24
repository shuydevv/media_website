<?php

namespace App\Service\Diagnostic;

/**
 * Проверка ответа по механике вопроса (ТЗ, раздел 6) и то, что о вопросе
 * можно показать до ответа. Чистая функция: вопрос — массив из банка
 * (QuestionBank), ответ — то, что прислала страница.
 *
 *   swipe   — {value: bool}
 *   single  — {id: "b"}
 *   multi   — {ids: ["a", "c"]}           засчитывается точное множество
 *   buckets — {mapping: {card: bucket}}   засчитывается только 100%
 *   chips   — {gaps: {"1": chip}}         все пропуски верно
 */
final class AnswerChecker
{
    public const MECHANICS = ['swipe', 'single', 'multi', 'buckets', 'chips'];

    public static function check(array $q, array $answer): bool
    {
        return match ($q['mechanic']) {
            'swipe' => isset($answer['value']) && is_bool($answer['value']) && $answer['value'] === $q['answer'],
            'single' => isset($answer['id']) && (string) $answer['id'] === (string) $q['correct'],
            'multi' => self::sameSet((array) ($answer['ids'] ?? []), $q['correct']),
            'buckets' => self::sameMap((array) ($answer['mapping'] ?? []), $q['mapping']),
            'chips' => self::sameMap((array) ($answer['gaps'] ?? []), $q['answers']),
            default => false,
        };
    }

    /** Правильный ответ — страница подсвечивает его в пояснении. */
    public static function solution(array $q): array
    {
        return match ($q['mechanic']) {
            'swipe' => ['value' => $q['answer']],
            'single' => ['id' => $q['correct']],
            'multi' => ['ids' => array_values($q['correct'])],
            'buckets' => ['mapping' => $q['mapping']],
            'chips' => ['gaps' => $q['answers']],
            default => [],
        };
    }

    /** Вопрос без ключей ответа и пояснений — то, что уходит на страницу до ответа. */
    public static function publicView(array $q): array
    {
        return array_diff_key($q, array_flip([
            'answer', 'correct', 'mapping', 'answers', 'explain_correct', 'explain_wrong', 'block', 'level',
        ]));
    }

    private static function sameSet(array $given, array $expected): bool
    {
        $given = array_values(array_unique(array_map('strval', $given)));
        $expected = array_values(array_unique(array_map('strval', $expected)));
        sort($given);
        sort($expected);

        return $given === $expected;
    }

    private static function sameMap(array $given, array $expected): bool
    {
        if (count($given) !== count($expected)) {
            return false;
        }
        foreach ($expected as $key => $value) {
            if (! array_key_exists($key, $given) || (string) $given[$key] !== (string) $value) {
                return false;
            }
        }

        return true;
    }
}
