<?php

namespace App\Service\Telegram;

use App\Models\TelegramText;
use Illuminate\Support\Facades\Cache;

/**
 * Тексты автосообщений: правка из админки (telegram_texts) поверх значений
 * по умолчанию из config/telegram_funnel.php. Текст пишется обычным текстом —
 * экранируем сами, иначе одна случайная «<» в тексте из админки валила бы
 * отправку целиком (Telegram отвечает 400 на невалидный HTML).
 */
final class FunnelTexts
{
    private const CACHE_KEY = 'telegram_funnel.texts';

    /** @return array<string, string> ключ → текст (сохранённый или по умолчанию) */
    public static function all(): array
    {
        $saved = Cache::remember(self::CACHE_KEY, now()->addHour(), fn () => TelegramText::pluck('text', 'key')->all());

        $texts = [];
        foreach (config('telegram_funnel.texts') as $key => $def) {
            $texts[$key] = $saved[$key] ?? $def['default'];
        }

        return $texts;
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Готовый HTML для sendMessage: экранированный текст с подстановками и,
     * для «человеческих» сообщений, подписью Александра первой строкой.
     */
    public static function render(string $key, array $vars = []): string
    {
        $text = self::all()[$key] ?? '';

        $replace = [];
        foreach ($vars as $name => $value) {
            $replace['{'.$name.'}'] = (string) $value;
        }
        $html = e(strtr($text, $replace));

        if (config("telegram_funnel.texts.{$key}.human")) {
            $html = config('telegram_funnel.signature').$html;
        }

        return $html;
    }
}
