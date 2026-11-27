<?php

namespace App\Service\LiveChat;

use Carbon\CarbonInterface;

/**
 * В каком состоянии чат урока прямо сейчас — чистая функция без БД.
 *
 *   waiting — до урока: «чат откроется в 18:45»;
 *   open    — пишут (за 15 мин до урока по расписанию ИЛИ эфир идёт);
 *   break   — Kinescope сказал «эфир закончился», 10 мин ещё можно писать
 *             («эфир прервался, ждём преподавателя»);
 *   closed  — только чтение / режим записи.
 *
 * Кнопок старт/стоп нет намеренно: преподаватель может забыть их нажать,
 * а урок — идти дольше расписания (поэтому закрытие — по сигналу Kinescope,
 * а не по времени конца урока). Время конца урока — только страховка, если
 * сигнал не пришёл совсем.
 */
final class ChatState
{
    public const WAITING = 'waiting';

    public const OPEN = 'open';

    public const BREAK = 'break';

    public const CLOSED = 'closed';

    /**
     * @param  array<int, array{0: CarbonInterface, 1: ?CarbonInterface}>  $segments  куски эфира [начало, конец|null]
     * @return array{state: string, opens_at: ?CarbonInterface, break_until: ?CarbonInterface}
     */
    public static function resolve(
        CarbonInterface $now,
        ?CarbonInterface $lessonStart,
        ?CarbonInterface $lessonEnd,
        array $segments,
        ?CarbonInterface $closedAt,
        array $cfg,
    ): array {
        $opensAt = $lessonStart?->copy()->subMinutes($cfg['opens_before_minutes']);
        $result = fn (string $s, $breakUntil = null) => ['state' => $s, 'opens_at' => $opensAt, 'break_until' => $breakUntil];

        $fallbackAt = $lessonEnd?->copy()->addHours($cfg['fallback_close_hours']);
        $open = null;
        $lastEnd = null;
        foreach ($segments as [$start, $end]) {
            if ($end === null) {
                $open = $start;
            } elseif ($lastEnd === null || $end->gt($lastEnd)) {
                $lastEnd = $end;
            }
        }

        if ($open !== null) {
            // Сигнал конца мог потеряться — тогда «эфир идёт» вечно. Страховка:
            // очень долгий эфир, уже после страховочного закрытия по расписанию.
            $stuck = $now->gte($open->copy()->addHours($cfg['stuck_live_hours']))
                && ($fallbackAt === null || $now->gte($fallbackAt));

            return $stuck ? $result(self::CLOSED) : $result(self::OPEN);
        }

        if ($closedAt !== null) {
            return $result(self::CLOSED);
        }

        if ($lastEnd !== null) {
            $breakUntil = $lastEnd->copy()->addMinutes($cfg['break_grace_minutes']);

            return $now->lt($breakUntil) ? $result(self::BREAK, $breakUntil) : $result(self::CLOSED);
        }

        if ($fallbackAt !== null && $now->gte($fallbackAt)) {
            return $result(self::CLOSED);
        }

        if ($opensAt !== null && $now->gte($opensAt)) {
            return $result(self::OPEN);
        }

        return $result(self::WAITING);
    }

    public static function canWrite(string $state): bool
    {
        return $state === self::OPEN || $state === self::BREAK;
    }
}
