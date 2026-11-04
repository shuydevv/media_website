<?php

namespace App\Service\Dates;

/**
 * Способ А тренажёра дат: раскладка годов по клеткам и проверка ответа.
 * Чистая логика без БД.
 *
 * Ученик вводит год, а у диапазона — год начала и год окончания подряд:
 * клетки «_ _ _ _ — _ _ _ _», тире между ними рисует страница. У текущего
 * правления года окончания нет — вводится только начало. Опечатки не
 * прощаются: 1707 и 1709 — разные годы.
 */
class YearAnswer
{
    /**
     * Цифры для клеток: год начала, затем год окончания (если он вводится).
     *
     * @return string[]
     */
    public function cells(int $start, ?int $end): array
    {
        return str_split($this->expected($start, $end));
    }

    /** Сколько клеток у года начала — после них страница ставит тире. */
    public function splitAt(int $start, ?int $end): ?int
    {
        return $this->isRange($start, $end) ? strlen((string) $start) : null;
    }

    public function isRange(int $start, ?int $end): bool
    {
        return $end !== null && $end !== $start;
    }

    public function expected(int $start, ?int $end): string
    {
        return $this->isRange($start, $end) ? $start.$end : (string) $start;
    }

    /** Только цифры из введённого: тире, пробелы и прочее не считаются. */
    public function digits(string $typed): string
    {
        return preg_replace('/\D+/', '', $typed);
    }

    public function matches(string $typed, int $start, ?int $end): bool
    {
        return $this->digits($typed) === $this->expected($start, $end);
    }

    /**
     * Введённые цифры как годы элемента той же формы: [начало, конец|null].
     * null — ввели не столько цифр, сколько клеток.
     *
     * @return array{0: int, 1: ?int}|null
     */
    public function parse(string $typed, int $start, ?int $end): ?array
    {
        $digits = $this->digits($typed);
        if (strlen($digits) !== strlen($this->expected($start, $end))) {
            return null;
        }
        $split = $this->splitAt($start, $end);

        return $split === null
            ? [(int) $digits, null]
            : [(int) substr($digits, 0, $split), (int) substr($digits, $split)];
    }

    /**
     * На сколько лет ошибка: у диапазона — наибольший промах из двух годов.
     * null — ввод не разобрать.
     */
    public function missBy(string $typed, int $start, ?int $end): ?int
    {
        $parsed = $this->parse($typed, $start, $end);
        if ($parsed === null) {
            return null;
        }
        $miss = abs($parsed[0] - $start);
        if ($parsed[1] !== null) {
            $miss = max($miss, abs($parsed[1] - (int) $end));
        }

        return $miss;
    }
}
