<?php

namespace App\Service\Diagnostic;

/**
 * Адаптивный путь внутри одного блока (ТЗ, раздел 5.2). Чистая функция от
 * уже данных в блоке ответов — без БД, см. tests/Unit/Service/Diagnostic.
 *
 *   блок начинается с лёгкого (1);
 *   верно → уровень выше (выше сложного, 3, — снова сложный);
 *   неверно после верного (или первым вопросом) → тот же уровень ещё раз;
 *   неверно второй раз подряд → блок закрыт;
 *   не больше 4 вопросов.
 *
 * Итог блока — наивысший уровень, на котором дан верный ответ (0 — ни
 * одного). Нижняя граница диапазона считает только верные ответы без
 * «Наугад».
 */
final class BlockRun
{
    public const MAX_QUESTIONS = 4;

    public const EASY = 1;

    public const HARD = 3;

    /**
     * Уровень следующего вопроса или null — блок закончен.
     *
     * @param  list<array{level: int, correct: bool}>  $answers
     */
    public static function nextLevel(array $answers): ?int
    {
        $count = count($answers);
        if ($count === 0) {
            return self::EASY;
        }
        if ($count >= self::MAX_QUESTIONS) {
            return null;
        }

        $last = $answers[$count - 1];
        if ($last['correct']) {
            return min($last['level'] + 1, self::HARD);
        }

        return self::closedByErrors($answers) ? null : $last['level'];
    }

    /**
     * Блок закрыт двумя ошибками подряд — для мягкого экрана «точка роста».
     *
     * @param  list<array{correct: bool}>  $answers
     */
    public static function closedByErrors(array $answers): bool
    {
        $count = count($answers);

        return $count >= 2 && ! $answers[$count - 1]['correct'] && ! $answers[$count - 2]['correct'];
    }

    /**
     * @param  list<array{level: int, correct: bool, guess?: bool}>  $answers
     * @param  bool  $confidentOnly  не считать верные ответы с «Наугад»
     */
    public static function result(array $answers, bool $confidentOnly = false): int
    {
        $best = 0;
        foreach ($answers as $a) {
            if ($a['correct'] && ! ($confidentOnly && ($a['guess'] ?? false))) {
                $best = max($best, $a['level']);
            }
        }

        return $best;
    }
}
