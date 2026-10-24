<?php

namespace App\Service\Diagnostic;

/**
 * Прогноз балла по итогам блоков (ТЗ, раздел 7). Чистая функция, все числа —
 * config('diagnostic.blocks') и config('diagnostic.model').
 *
 *   первая часть — по измерению: Σ part1_max блока × освоение[уровень];
 *   вторая — оценка: part2_max × k × среднее освоение;
 *   первичный → тестовый — по шкале (линейно между точками).
 *
 * Диапазон: балл по всем верным ±min_spread; если по уровням без верных
 * «Наугад» выходит ниже — нижняя граница опускается до него. Прибавка блока — сколько тестовых баллов
 * даст поднять его до сложного уровня; потенциал — два самых «дорогих»
 * блока подняты.
 */
final class ScoreModel
{
    /**
     * @param  array<string, array{title: string, part1_max: int|float}>  $blocks
     * @param  array{mastery: array<int, float>, part2_max: int|float, part2_k: float, scale: array<int, int>, min_spread: int}  $model
     */
    public function __construct(private readonly array $blocks, private readonly array $model)
    {
    }

    public static function fromConfig(): self
    {
        return new self(config('diagnostic.blocks'), config('diagnostic.model'));
    }

    /**
     * @param  array<string, int>  $high  блок → итог по всем верным ответам
     * @param  array<string, int>  $low  блок → итог без верных «Наугад»
     * @return array{low: int, high: int, potential: int, gains: array<string, int>, weak: string[]}
     */
    public function compute(array $high, array $low): array
    {
        $highScore = $this->testScore($high);
        $lowScore = $this->testScore($low);
        $spread = $this->model['min_spread'];

        // ±spread вокруг балла по всем верным; «Наугад» опускает только нижнюю
        // границу — верхняя не должна падать оттого, что ученик честно сомневался.
        $from = min($lowScore, $highScore - $spread);
        $to = $highScore + $spread;

        $gains = [];
        foreach (array_keys($this->blocks) as $block) {
            $gainHigh = $this->testScore([$block => BlockRun::HARD] + $high) - $highScore;
            $gainLow = $this->testScore([$block => BlockRun::HARD] + $low) - $lowScore;
            $gains[$block] = (int) round(($gainHigh + $gainLow) / 2);
        }
        // Дорогие вперёд; при равенстве — порядок блоков из конфига.
        $order = array_flip(array_keys($this->blocks));
        uksort($gains, fn ($a, $b) => [$gains[$b], $order[$a]] <=> [$gains[$a], $order[$b]]);

        $weak = array_slice(array_keys(array_filter($gains, fn ($g) => $g > 0)), 0, 2);
        $lifted = $high;
        foreach ($weak as $block) {
            $lifted[$block] = BlockRun::HARD;
        }

        return [
            'low' => (int) max(0, round($from)),
            'high' => (int) min(100, round($to)),
            'potential' => (int) min(100, round($this->testScore($lifted))),
            'gains' => $gains,
            'weak' => $weak,
        ];
    }

    /** @param  array<string, int>  $levels */
    public function primary(array $levels): float
    {
        $mastery = $this->model['mastery'];
        $part1 = 0.0;
        $sum = 0.0;
        foreach ($this->blocks as $block => $def) {
            $m = $mastery[$levels[$block] ?? 0];
            $part1 += $def['part1_max'] * $m;
            $sum += $m;
        }
        $average = $this->blocks === [] ? 0 : $sum / count($this->blocks);

        return $part1 + $this->model['part2_max'] * $this->model['part2_k'] * $average;
    }

    /** @param  array<string, int>  $levels */
    public function testScore(array $levels): float
    {
        return $this->scale($this->primary($levels));
    }

    private function scale(float $primary): float
    {
        $points = $this->model['scale'];
        ksort($points);
        $prevP = null;
        $prevT = null;
        foreach ($points as $p => $t) {
            if ($primary <= $p) {
                if ($prevP === null) {
                    return (float) $t;
                }

                return $prevT + ($t - $prevT) * ($primary - $prevP) / ($p - $prevP);
            }
            [$prevP, $prevT] = [$p, $t];
        }

        return (float) $prevT;
    }
}
