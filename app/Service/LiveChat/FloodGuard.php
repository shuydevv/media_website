<?php

namespace App\Service\LiveChat;

/**
 * Медленный режим, который включается сам для одного ученика — чистая
 * функция по истории его последних сообщений в чате урока.
 *
 * Флуд — N сообщений за M секунд или K одинаковых подряд. После флуда писать
 * можно раз в slow_seconds; режим снимается, когда с последнего флуда прошло
 * calm_seconds. В медленном режиме новый «залп» невозможен, поэтому отдельно
 * хранить «включён до» не нужно — всё выводится из истории.
 */
final class FloodGuard
{
    /**
     * @param  array<int, array{0: int, 1: string}>  $recent  [unix-время, текст] — последние сообщения, старые → новые
     * @param  string  $next  текст, который ученик хочет отправить
     * @return array{allowed: bool, slow: bool, wait: int}
     */
    public static function check(array $recent, string $next, int $now, array $cfg): array
    {
        $floodAt = self::lastFloodAt($recent, $cfg);
        $slow = $floodAt !== null && $now - $floodAt < $cfg['calm_seconds'];

        if ($slow) {
            $last = $recent[count($recent) - 1][0];
            $wait = $last + $cfg['slow_seconds'] - $now;
            if ($wait > 0) {
                return ['allowed' => false, 'slow' => true, 'wait' => $wait];
            }

            return ['allowed' => true, 'slow' => true, 'wait' => $cfg['slow_seconds']];
        }

        // Это сообщение само становится флудом — пропускаем его, но дальше уже
        // медленный режим (ученик видит таймер сразу после отправки).
        $with = array_merge($recent, [[$now, $next]]);
        $becomes = self::lastFloodAt($with, $cfg) === $now;

        return ['allowed' => true, 'slow' => $becomes, 'wait' => $becomes ? $cfg['slow_seconds'] : 0];
    }

    /** Время сообщения, которым закончился последний флуд, или null. */
    public static function lastFloodAt(array $msgs, array $cfg): ?int
    {
        $found = null;
        $n = count($msgs);
        for ($i = 0; $i < $n; $i++) {
            $t = $msgs[$i][0];

            $b = $cfg['burst_count'];
            if ($i + 1 >= $b && $t - $msgs[$i - $b + 1][0] <= $cfg['burst_seconds']) {
                $found = $t;

                continue;
            }

            $r = $cfg['repeat_count'];
            if ($i + 1 >= $r) {
                $norm = self::norm($msgs[$i][1]);
                $same = true;
                for ($j = $i - $r + 1; $j < $i; $j++) {
                    if (self::norm($msgs[$j][1]) !== $norm) {
                        $same = false;
                        break;
                    }
                }
                if ($same) {
                    $found = $t;
                }
            }
        }

        return $found;
    }

    private static function norm(string $s): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($s)));
    }
}
