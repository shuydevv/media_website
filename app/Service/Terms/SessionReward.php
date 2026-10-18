<?php

namespace App\Service\Terms;

/**
 * Корм за законченную сессию тренажёра — по доле верных с первой попытки.
 * Считается долей, а не числом ошибок: сессии бывают от 2 до 20 терминов.
 */
class SessionReward
{
    /**
     * @param array<int, int> $thresholds минимальный процент => корм
     */
    public function __construct(private array $thresholds)
    {
        krsort($this->thresholds);
    }

    public static function fromConfig(): self
    {
        return new self(config('terms.corm'));
    }

    public function corm(int $correctFirst, int $total): int
    {
        if ($total <= 0) {
            return 0;
        }

        // Целочисленное сравнение без деления: 4 из 5 — ровно 80%, и
        // округление float не должно уронить это в нижнюю ступень.
        foreach ($this->thresholds as $percent => $corm) {
            if ($correctFirst * 100 >= $percent * $total) {
                return (int) $corm;
            }
        }

        return 0;
    }
}
