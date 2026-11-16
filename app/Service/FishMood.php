<?php

namespace App\Service;

use Carbon\CarbonInterface;

/**
 * Полосы «Сытость» и «Радость» у акулы — чистая арифметика без БД.
 * Хранится значение на момент $at; текущее = сохранённое минус падение за
 * прошедшее время (config('fish.mood.decay_per_day') в сутки, плавно, а не
 * ступенькой в полночь). Прибавка — от текущего значения, не от
 * сохранённого. Где полосы растут — FishFoodService.
 */
class FishMood
{
    public const MAX = 100.0;

    public function __construct(private float $decayPerDay)
    {
    }

    public static function fromConfig(): self
    {
        return new self((float) config('fish.mood.decay_per_day', 25));
    }

    /** Текущее значение, 0–100. $at null — отсчёт ещё не начат (полоса как есть). */
    public function current(float $stored, ?CarbonInterface $at, CarbonInterface $now): float
    {
        if ($at === null) {
            return $this->clamp($stored);
        }

        $days = max(0, $at->diffInSeconds($now, false)) / 86400;

        return $this->clamp($stored - $days * $this->decayPerDay);
    }

    /** Новое значение после прибавки $amount к текущему. */
    public function add(float $stored, ?CarbonInterface $at, CarbonInterface $now, float $amount): float
    {
        return $this->clamp($this->current($stored, $at, $now) + $amount);
    }

    private function clamp(float $value): float
    {
        return round(max(0.0, min(self::MAX, $value)), 2);
    }
}
