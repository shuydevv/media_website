<?php

namespace App\Service\Telegram;

/**
 * Логин Telegram из формы на сайте в каноническом виде (без @). Люди пишут
 * его как угодно: «@ivan», «ivan», «t.me/ivan», «https://t.me/ivan», с
 * пробелами — всё это один и тот же логин. По правилам Telegram логин —
 * 5–32 символа: латиница, цифры, подчёркивание, начинается с буквы.
 *
 * Чистая функция — см. tests/Unit/Service/Telegram/TelegramUsernameTest.
 */
final class TelegramUsername
{
    public static function normalize(string $input): ?string
    {
        $value = trim($input);
        $value = preg_replace('~^(?:https?://)?(?:www\.)?(?:t\.me|telegram\.me)/~i', '', $value);
        $value = ltrim((string) $value, '@');
        $value = rtrim($value, '/');

        return preg_match('/^[A-Za-z][A-Za-z0-9_]{4,31}$/', $value) ? $value : null;
    }
}
