<?php

namespace App\Service\Telegram;

/**
 * Разбор параметра deep link t.me/bot?start=… (приходит как «/start <param>»).
 * Формат: «<код урока>_<метка рекламы>», например cho_vk_ad1 → урок cho,
 * метка vk_ad1. Без известного кода урока весь параметр считается меткой,
 * урок — по умолчанию: ссылки без кода урока (или со старым форматом) не
 * должны ломаться, человек всё равно получает урок.
 *
 * Чистая функция без БД и конфига — см. tests/Unit/Service/Telegram.
 */
final class StartParam
{
    /**
     * @param  string[]  $lessonCodes
     * @return array{lesson: string, source: ?string}
     */
    public static function parse(?string $param, array $lessonCodes, string $defaultLesson): array
    {
        $param = trim((string) $param);
        // Telegram и так пропускает только [A-Za-z0-9_-], но параметр можно
        // отправить и руками текстом «/start что угодно».
        $param = substr(preg_replace('/[^A-Za-z0-9_-]/', '', $param) ?? '', 0, 64);

        if ($param === '') {
            return ['lesson' => $defaultLesson, 'source' => null];
        }

        if (in_array($param, $lessonCodes, true)) {
            return ['lesson' => $param, 'source' => null];
        }

        $prefix = strstr($param, '_', true);
        if ($prefix !== false && in_array($prefix, $lessonCodes, true)) {
            $source = substr($param, strlen($prefix) + 1);

            return ['lesson' => $prefix, 'source' => $source !== '' ? $source : null];
        }

        return ['lesson' => $defaultLesson, 'source' => $param];
    }
}
