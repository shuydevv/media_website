<?php

namespace Tests\Unit\Service\LiveChat;

use App\Service\LiveChat\ChatState;
use App\Service\LiveChat\FloodGuard;
use App\Service\LiveChat\ReplayClock;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/** Чистая логика чата трансляций — без БД и без Laravel. */
class LiveChatLogicTest extends TestCase
{
    private array $cfg = [
        'opens_before_minutes' => 15, 'break_grace_minutes' => 10,
        'fallback_close_hours' => 4, 'stuck_live_hours' => 8,
    ];

    private array $flood = [
        'burst_count' => 5, 'burst_seconds' => 10, 'repeat_count' => 3,
        'slow_seconds' => 30, 'calm_seconds' => 120,
    ];

    private function at(string $t): Carbon
    {
        return Carbon::parse('2026-12-01 '.$t);
    }

    private function state(string $now, array $segments = [], ?string $closed = null): string
    {
        return ChatState::resolve(
            $this->at($now), $this->at('19:00'), $this->at('20:30'),
            array_map(fn ($s) => [$this->at($s[0]), $s[1] ? $this->at($s[1]) : null], $segments),
            $closed ? $this->at($closed) : null, $this->cfg,
        )['state'];
    }

    public function test_opens_15_minutes_before_lesson(): void
    {
        $this->assertSame('waiting', $this->state('18:44'));
        $this->assertSame('open', $this->state('18:45'));
    }

    public function test_live_stream_opens_chat_even_before_schedule_and_after_closing(): void
    {
        $this->assertSame('open', $this->state('18:00', [['17:55', null]]));
        $this->assertSame('open', $this->state('22:00', [['19:00', '20:00'], ['21:50', null]], '20:10'));
    }

    public function test_end_signal_gives_10_minute_break_then_closes(): void
    {
        $this->assertSame('break', $this->state('21:05', [['19:00', '21:00']]));
        $this->assertSame('closed', $this->state('21:10', [['19:00', '21:00']]));
    }

    public function test_lesson_longer_than_schedule_stays_open_while_live(): void
    {
        $this->assertSame('open', $this->state('23:00', [['19:00', null]]));
    }

    public function test_fallback_closes_4_hours_after_lesson_end_without_signals(): void
    {
        $this->assertSame('open', $this->state('20:00'));
        $this->assertSame('closed', ChatState::resolve(
            $this->at('20:30')->addHours(4), $this->at('19:00'), $this->at('20:30'), [], null, $this->cfg,
        )['state']);
    }

    public function test_stuck_live_signal_is_closed_only_after_both_safety_limits(): void
    {
        $segments = [[$this->at('19:00'), null]];
        $s = fn ($now) => ChatState::resolve($now, $this->at('19:00'), $this->at('20:30'), $segments, null, $this->cfg)['state'];
        $this->assertSame('open', $s($this->at('19:00')->addHours(5)));
        $this->assertSame('closed', $s($this->at('19:00')->addHours(8)));
    }

    public function test_flood_burst_turns_on_slow_mode(): void
    {
        $recent = [[100, 'a'], [101, 'b'], [102, 'c'], [103, 'd']];
        $r = FloodGuard::check($recent, 'e', 104, $this->flood);
        $this->assertTrue($r['allowed']);
        $this->assertTrue($r['slow']);
        $this->assertSame(30, $r['wait']);

        $recent[] = [104, 'e'];
        $this->assertFalse(FloodGuard::check($recent, 'f', 110, $this->flood)['allowed']);
        $this->assertTrue(FloodGuard::check($recent, 'f', 134, $this->flood)['allowed']);
        // 2 минуты без флуда — режим снят
        $this->assertSame(['allowed' => true, 'slow' => false, 'wait' => 0], FloodGuard::check($recent, 'f', 225, $this->flood));
    }

    public function test_three_identical_messages_count_as_flood(): void
    {
        $r = FloodGuard::check([[0, 'Привет'], [40, 'привет ']], 'ПРИВЕТ', 80, $this->flood);
        $this->assertTrue($r['slow']);
        $this->assertFalse(FloodGuard::check([[0, 'да'], [40, 'нет']], 'да', 80, $this->flood)['slow']);
    }

    public function test_normal_pace_is_never_slowed(): void
    {
        $recent = [[0, 'a'], [5, 'b'], [10, 'c'], [15, 'd']];
        $this->assertSame(['allowed' => true, 'slow' => false, 'wait' => 0], FloodGuard::check($recent, 'e', 20, $this->flood));
    }

    public function test_replay_clock_skips_gaps_between_segments(): void
    {
        $c = new ReplayClock([[1000, 2000], [2600, null]], true);
        $this->assertSame(0, $c->offset(900));      // до эфира — в начале
        $this->assertSame(500, $c->offset(1500));
        $this->assertSame(1000, $c->offset(2300));  // в обрыве — на стыке
        $this->assertSame(1100, $c->offset(2700));

        $plain = new ReplayClock([[1000, 2000], [2600, null]], false);
        $this->assertSame(1700, $plain->offset(2700));

        $this->assertSame(60, (new ReplayClock([], true, 1000))->offset(1060));
    }
}
