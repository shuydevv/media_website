<?php

namespace App\Service\LiveChat;

/**
 * Где в записи эфира стоит сообщение — секунда от начала записи. Запись —
 * автоматическая запись эфира Kinescope, она начинается со старта эфира.
 *
 * Куски эфира [начало, конец] в unix-секундах, по возрастанию. При skipGaps
 * время без эфира между кусками не считается (запись склеена без паузы),
 * сообщения из обрыва встают на стык. До эфира — 0 (видны сразу), после — в
 * конец. Без сигналов Kinescope — от начала урока по расписанию.
 */
final class ReplayClock
{
    /** @param array<int, array{0: int, 1: ?int}> $segments */
    public function __construct(private array $segments, private bool $skipGaps, private ?int $fallbackOrigin = null)
    {
    }

    public function offset(int $t): int
    {
        if (! $this->segments) {
            return $this->fallbackOrigin === null ? 0 : max(0, $t - $this->fallbackOrigin);
        }

        if (! $this->skipGaps) {
            return max(0, $t - $this->segments[0][0]);
        }

        $acc = 0;
        foreach ($this->segments as [$start, $end]) {
            if ($t < $start) {
                return $acc;
            }
            if ($end === null || $t <= $end) {
                return $acc + ($t - $start);
            }
            $acc += $end - $start;
        }

        return $acc;
    }
}
