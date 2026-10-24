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
 *   fix     — {spots: {spot: слово}}      «Найди и исправь»: каждое
 *             спорное место текста должно стоять верно; не тронутое — как
 *             было показано (shown). Угадать трудно: надо и найти ошибки,
 *             и выбрать замену из похожих терминов.
 *
 * Пропуски в тексте (chips) убраны: по смыслу и грамматике подходило одно
 * слово, угадывалось слишком легко.
 */
final class AnswerChecker
{
    public const MECHANICS = ['swipe', 'single', 'multi', 'buckets', 'fix'];

    public static function check(array $q, array $answer): bool
    {
        return match ($q['mechanic']) {
            'swipe' => isset($answer['value']) && is_bool($answer['value']) && $answer['value'] === $q['answer'],
            'single' => isset($answer['id']) && (string) $answer['id'] === (string) $q['correct'],
            'multi' => self::sameSet((array) ($answer['ids'] ?? []), $q['correct']),
            'buckets' => self::sameMap((array) ($answer['mapping'] ?? []), $q['mapping']),
            'fix' => self::fixed((array) ($answer['spots'] ?? []), $q['spots']),
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
            'fix' => ['spots' => array_column($q['spots'], 'correct', 'id')],
            default => [],
        };
    }

    /** Вопрос без ключей ответа и пояснений — то, что уходит на страницу до ответа. */
    public static function publicView(array $q): array
    {
        $view = array_diff_key($q, array_flip([
            'answer', 'correct', 'mapping', 'answers', 'explain_correct', 'explain_wrong', 'block', 'level',
        ]));
        if (isset($view['spots'])) {
            $view['spots'] = array_map(fn ($s) => array_diff_key($s, ['correct' => true]), $view['spots']);
        }

        return $view;
    }

    private static function fixed(array $given, array $spots): bool
    {
        foreach ($spots as $spot) {
            $chosen = (string) ($given[$spot['id']] ?? $spot['shown']);
            if ($chosen !== (string) $spot['correct']) {
                return false;
            }
        }

        return true;
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
