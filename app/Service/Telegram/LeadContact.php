<?php

namespace App\Service\Telegram;

/**
 * Поле «Логин в Telegram или телефон» из формы «Записаться»: логина в
 * Telegram у многих нет, поэтому принимаем и номер. Номер приводим к виду
 * +79991234567 — по нему админ откроет чат через t.me/+7…, если человек не
 * скрыл номер в настройках приватности (поэтому в форме и предупреждение).
 *
 * Чистая функция — см. tests/Unit/Service/Telegram/LeadContactTest.
 */
final class LeadContact
{
    /** @return array{telegram: ?string, phone: ?string}|null null — не похоже ни на логин, ни на номер */
    public static function parse(string $input): ?array
    {
        $value = trim($input);

        // Похоже на номер: только цифры и разделители, + в начале.
        if (preg_match('/^\+?[\d\s()\-]+$/', $value)) {
            $digits = preg_replace('/\D/', '', $value);

            if (strlen($digits) === 11 && ($digits[0] === '8' || $digits[0] === '7')) {
                $digits = '7'.substr($digits, 1);
            } elseif (strlen($digits) === 10 && $digits[0] === '9') {
                $digits = '7'.$digits;
            } elseif (! str_starts_with($value, '+') || strlen($digits) < 10 || strlen($digits) > 15) {
                return null;
            }

            return ['telegram' => null, 'phone' => '+'.$digits];
        }

        $username = TelegramUsername::normalize($value);

        return $username !== null ? ['telegram' => $username, 'phone' => null] : null;
    }
}
