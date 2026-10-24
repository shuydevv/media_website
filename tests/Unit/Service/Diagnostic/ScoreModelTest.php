<?php

namespace Tests\Unit\Service\Diagnostic;

use App\Service\Diagnostic\ScoreModel;
use PHPUnit\Framework\TestCase;

class ScoreModelTest extends TestCase
{
    private function model(array $scale = [0 => 0, 58 => 100]): ScoreModel
    {
        return new ScoreModel(
            [
                'a' => ['title' => 'А', 'part1_max' => 6],
                'b' => ['title' => 'Б', 'part1_max' => 6],
                'c' => ['title' => 'В', 'part1_max' => 4],
            ],
            ['mastery' => [0 => 0.0, 1 => 0.5, 2 => 0.75, 3 => 1.0], 'part2_max' => 10, 'part2_k' => 1.0, 'scale' => $scale, 'min_spread' => 3],
        );
    }

    public function test_primary_is_part1_plus_estimated_part2(): void
    {
        // 6×1 + 6×0.5 + 4×0 = 9; часть 2: 10 × 1 × (1 + 0.5 + 0) / 3 = 5
        $this->assertEqualsWithDelta(14.0, $this->model()->primary(['a' => 3, 'b' => 1, 'c' => 0]), 0.001);
    }

    public function test_scale_interpolates_between_points(): void
    {
        $model = $this->model([0 => 0, 10 => 40, 20 => 60]);

        // первичный 6 + 10 × 1/3 = 9.33 → между 0 и 10: 40 × 0.933
        $this->assertEqualsWithDelta(37.33, $model->testScore(['a' => 3, 'b' => 0, 'c' => 0]), 0.01);
        // все на нуле: часть 1 = 0, часть 2 = 0 → 0
        $this->assertEqualsWithDelta(0.0, $model->testScore(['a' => 0, 'b' => 0, 'c' => 0]), 0.001);
        // выше последней точки — последнее значение
        $this->assertEqualsWithDelta(60.0, $model->testScore(['a' => 3, 'b' => 3, 'c' => 3]), 0.001);
    }

    public function test_range_is_at_least_min_spread_wide(): void
    {
        $r = $this->model()->compute(['a' => 2, 'b' => 2, 'c' => 2], ['a' => 2, 'b' => 2, 'c' => 2]);

        $this->assertSame(6, $r['high'] - $r['low']);
    }

    public function test_guesses_widen_the_range_downwards(): void
    {
        $same = $this->model()->compute(['a' => 3, 'b' => 2, 'c' => 2], ['a' => 3, 'b' => 2, 'c' => 2]);
        $guessed = $this->model()->compute(['a' => 3, 'b' => 2, 'c' => 2], ['a' => 1, 'b' => 2, 'c' => 2]);

        $this->assertLessThan($same['low'], $guessed['low']);
        $this->assertSame($same['high'], $guessed['high']);
    }

    public function test_gains_sorted_and_potential_lifts_two_weakest(): void
    {
        $model = $this->model();
        $levels = ['a' => 3, 'b' => 0, 'c' => 1];
        $r = $model->compute($levels, $levels);

        $this->assertSame(['b', 'c', 'a'], array_keys($r['gains']));
        $this->assertSame(0, $r['gains']['a'], 'уже на сложном — прибавки нет');
        $this->assertSame(['b', 'c'], $r['weak']);
        $this->assertSame((int) round($model->testScore(['a' => 3, 'b' => 3, 'c' => 3])), $r['potential']);
    }

    public function test_strong_student_has_no_weak_blocks(): void
    {
        $levels = ['a' => 3, 'b' => 3, 'c' => 3];
        $r = $this->model()->compute($levels, $levels);

        $this->assertSame([], $r['weak']);
        $this->assertLessThanOrEqual(100, $r['high']);
    }

    public function test_potential_is_never_below_current_upper_bound(): void
    {
        // Подтягивать нечего: балл «подтянув слабые» без запаса ±3 — ниже верха диапазона.
        $levels = ['a' => 3, 'b' => 3, 'c' => 3];
        $r = $this->model([0 => 0, 58 => 80])->compute($levels, $levels);

        $this->assertGreaterThanOrEqual($r['high'], $r['potential']);
    }
}
