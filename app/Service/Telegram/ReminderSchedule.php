<?php

namespace App\Service\Telegram;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Когда слать напоминание «Получилось посмотреть урок?»: случайно через
 * 22–26 ч после старта, но только в окне 10:00–21:00. Если случайное время
 * выпало на вечер/ночь — переносим на утро (10:00 + до получаса разброса,
 * чтобы все ночные не уходили одной пачкой ровно в 10:00 и не выглядели
 * рассылкой).
 *
 * Время — в часовом поясе приложения (config app.timezone = Europe/Moscow).
 * Чистая функция: случайные минуты передаются снаружи, чтобы её можно было
 * проверить тестом (tests/Unit/Service/Telegram/ReminderScheduleTest).
 */
final class ReminderSchedule
{
    public static function at(
        CarbonInterface $startedAt,
        int $delayMinutes,
        int $morningJitterMinutes,
        int $windowStartHour = 10,
        int $windowEndHour = 21,
    ): CarbonImmutable {
        return self::fitWindow(CarbonImmutable::instance($startedAt)->addMinutes($delayMinutes), $morningJitterMinutes, $windowStartHour, $windowEndHour);
    }

    /** Время как есть, если оно в окне; иначе — ближайшее утро + разброс. */
    public static function fitWindow(
        CarbonInterface $at,
        int $morningJitterMinutes,
        int $windowStartHour = 10,
        int $windowEndHour = 21,
    ): CarbonImmutable {
        $at = CarbonImmutable::instance($at);
        $windowStart = $at->setTime($windowStartHour, 0);
        $windowEnd = $at->setTime($windowEndHour, 0);

        if ($at->lt($windowStart)) {
            return $windowStart->addMinutes($morningJitterMinutes);
        }

        if ($at->gte($windowEnd)) {
            return $windowStart->addDay()->addMinutes($morningJitterMinutes);
        }

        return $at;
    }

    /** fitWindow() с окном и разбросом из конфига. */
    public static function inWindow(CarbonInterface $at): CarbonImmutable
    {
        $t = config('telegram_funnel.timing');

        return self::fitWindow($at, random_int(0, 30), $t['window_start_hour'], $t['window_end_hour']);
    }

    /** То же со случайными значениями из конфига — для боевого кода. */
    public static function randomFor(CarbonInterface $startedAt): CarbonImmutable
    {
        $t = config('telegram_funnel.timing');

        return self::at(
            $startedAt,
            random_int($t['reminder_min_hours'] * 60, $t['reminder_max_hours'] * 60),
            random_int(0, 30),
            $t['window_start_hour'],
            $t['window_end_hour'],
        );
    }
}
