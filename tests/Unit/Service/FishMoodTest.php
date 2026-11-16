<?php

namespace Tests\Unit\Service;

use App\Service\FishMood;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class FishMoodTest extends TestCase
{
    public function test_decays_smoothly_and_stops_at_zero(): void
    {
        $mood = new FishMood(25);
        $at = Carbon::parse('2026-11-15 12:00');

        $this->assertSame(100.0, $mood->current(100, $at, $at));
        $this->assertSame(87.5, $mood->current(100, $at, $at->copy()->addHours(12)));
        $this->assertSame(50.0, $mood->current(100, $at, $at->copy()->addDays(2)));
        $this->assertSame(0.0, $mood->current(100, $at, $at->copy()->addDays(5)));
    }

    public function test_add_starts_from_current_value_and_caps_at_max(): void
    {
        $mood = new FishMood(25);
        $at = Carbon::parse('2026-11-15 12:00');

        // Сутки спустя: 100 → 75, +10 = 85.
        $this->assertSame(85.0, $mood->add(100, $at, $at->copy()->addDay(), 10));
        // Сверх 100 не растёт — кормить можно, полоса стоит.
        $this->assertSame(100.0, $mood->add(95, $at, $at, 10));
    }

    public function test_no_timestamp_means_no_decay_and_future_timestamp_is_safe(): void
    {
        $mood = new FishMood(25);
        $now = Carbon::parse('2026-11-15 12:00');

        $this->assertSame(60.0, $mood->current(60, null, $now));
        $this->assertSame(60.0, $mood->current(60, $now->copy()->addHour(), $now));
    }
}
