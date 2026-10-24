<?php

namespace App\Service\Telegram;

use App\Models\TelegramText;
use Illuminate\Support\Facades\Cache;

/**
 * Тексты автосообщений: правка из админки (telegram_texts) поверх значений
 * по умолчанию из config/telegram_funnel.php и config/telegram_diag.php (бот
 * диагностики, ключи diag_* — в той же таблице, см. definitions()). Текст пишется обычным текстом —
 * экранируем сами, иначе одна случайная «<» в тексте из админки валила бы
 * отправку целиком (Telegram отвечает 400 на невалидный HTML).
 */
final class FunnelTexts
{
    private const CACHE_KEY = 'telegram_funnel.texts';

    /**
     * Описания текстов обоих ботов: воронки и диагностики (ключи diag_*).
     * Ключи не пересекаются — одна таблица telegram_texts на всех.
     *
     * @return array<string, array{label: string, hint: string, human: bool, default: string}>
     */
    public static function definitions(): array
    {
        return config('telegram_funnel.texts') + (config('telegram_diag.texts') ?? []);
    }

    /** @return array<string, string> ключ → текст (сохранённый или по умолчанию) */
    public static function all(): array
    {
        $saved = Cache::remember(self::CACHE_KEY, now()->addHour(), fn () => TelegramText::pluck('text', 'key')->all());

        $texts = [];
        foreach (self::definitions() as $key => $def) {
            $texts[$key] = $saved[$key] ?? $def['default'];
        }

        return $texts;
    }

    /**
     * Подпись кнопки (btn_*): как есть, без экранирования — это не HTML.
     * Премиум-эмодзи на кнопке не бывают — {e:…} превращаем в заменитель.
     */
    public static function plain(string $key): string
    {
        $text = self::all()[$key] ?? '';

        return trim(preg_replace('/\{e:\d{5,25}:([^}\n]{1,16})\}/u', '$1', $text));
    }

    /** Подпись оценки (great / good / bad) — та же, что на кнопке. */
    public static function ratingLabel(?string $rating): string
    {
        return $rating ? self::plain('btn_'.$rating) : '—';
    }

    /** Есть ли в тексте ссылка [в скобках] — тогда отдельная кнопка урока не нужна. */
    public static function hasLink(string $key): bool
    {
        return (bool) preg_match('/\[[^\]\n]+\]/u', self::all()[$key] ?? '');
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Готовый HTML для sendMessage: экранированный текст с подстановками и,
     * для «человеческих» сообщений, подписью Александра первой строкой.
     */
    public static function render(string $key, array $vars = [], ?string $linkUrl = null): string
    {
        $text = self::all()[$key] ?? '';

        $replace = [];
        foreach ($vars as $name => $value) {
            $replace['{'.$name.'}'] = (string) $value;
        }
        $html = e(strtr($text, $replace));

        // [СМОТРЕТЬ] → ссылка на урок. После экранирования: квадратные скобки
        // e() не трогает, а адрес наш, не из текста админки.
        if ($linkUrl !== null) {
            $html = preg_replace('/\[([^\]\n]+)\]/u', '<a href="'.e($linkUrl).'">$1</a>', $html);
        }

        // {e:<id>:<эмодзи>} → премиум-эмодзи (коды — командой /emoji в
        // админ-группе). Эмодзи после двоеточия — заменитель: его видят, если
        // боту премиум-эмодзи недоступны.
        $html = preg_replace('/\{e:(\d{5,25}):([^}\n]{1,16})\}/u', '<tg-emoji emoji-id="$1">$2</tg-emoji>', $html);

        if (self::definitions()[$key]['human'] ?? false) {
            // Текст «от Александра» — жирным (ответы админа из темы — как есть).
            $html = config('telegram_funnel.signature').'<b>'.$html.'</b>';
        }

        return $html;
    }
}
