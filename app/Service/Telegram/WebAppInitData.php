<?php

namespace App\Service\Telegram;

/**
 * Проверка Telegram.WebApp.initData — подписанных Telegram данных о том, кто
 * открыл Mini App (https://core.telegram.org/bots/webapps#validating-data-received-via-the-mini-app).
 * Подпись — HMAC-SHA256 токеном того бота, чья кнопка открыла страницу,
 * поэтому подделать пользователя без токена нельзя.
 *
 * Чистая функция без БД и конфига — см. tests/Unit/Service/Telegram.
 */
final class WebAppInitData
{
    /**
     * @return array|null пользователь Telegram (id, first_name, username …) или
     *                    null — подпись не сошлась, данные старые или без user
     */
    public static function user(string $initData, string $botToken, int $maxAgeSeconds, ?int $now = null): ?array
    {
        if ($initData === '' || $botToken === '') {
            return null;
        }

        parse_str($initData, $fields);
        $hash = $fields['hash'] ?? null;
        if (! is_string($hash)) {
            return null;
        }
        unset($fields['hash']);

        ksort($fields);
        $check = implode("\n", array_map(fn ($k, $v) => "{$k}={$v}", array_keys($fields), $fields));
        $secret = hash_hmac('sha256', $botToken, 'WebAppData', true);
        if (! hash_equals(hash_hmac('sha256', $check, $secret), $hash)) {
            return null;
        }

        $authDate = (int) ($fields['auth_date'] ?? 0);
        if ($authDate <= 0 || ($now ?? time()) - $authDate > $maxAgeSeconds) {
            return null;
        }

        $user = json_decode((string) ($fields['user'] ?? ''), true);

        return is_array($user) && isset($user['id']) ? $user : null;
    }
}
