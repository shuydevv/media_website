<?php

namespace Tests\Unit\Service\Telegram;

use App\Service\Telegram\ReminderSchedule;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ReminderScheduleTest extends TestCase
{
    private function at(string $start, int $delayHours, int $jitter = 7): string
    {
        return ReminderSchedule::at(CarbonImmutable::parse($start), $delayHours * 60, $jitter)->format('Y-m-d H:i');
    }

    /** @test */
    public function time_inside_the_window_is_kept_as_is()
    {
        $this->assertSame('2026-10-15 14:00', $this->at('2026-10-14 14:00', 24));
        $this->assertSame('2026-10-15 10:00', $this->at('2026-10-14 12:00', 22));
    }

    /** @test */
    public function evening_time_moves_to_next_morning_with_jitter()
    {
        // 19:00 + 24 ч = 19:00 — ещё в окне; 22:00 + 23 ч = 21:00 — уже нет.
        $this->assertSame('2026-10-15 19:00', $this->at('2026-10-14 19:00', 24));
        $this->assertSame('2026-10-16 10:07', $this->at('2026-10-14 22:00', 23));
        $this->assertSame('2026-10-16 10:07', $this->at('2026-10-14 23:30', 23));
    }

    /** @test */
    public function night_time_moves_to_the_same_morning_with_jitter()
    {
        // 02:00 + 24 ч = 02:00 следующего дня → 10:07 того же дня.
        $this->assertSame('2026-10-15 10:07', $this->at('2026-10-14 02:00', 24));
        $this->assertSame('2026-10-15 10:07', $this->at('2026-10-14 09:59', 24));
    }
}
