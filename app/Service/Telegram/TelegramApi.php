<?php

namespace App\Service\Telegram;

use Illuminate\Support\Facades\Http;

/**
 * Тонкий клиент Bot API для бота воронки. Без SDK: нужно с десяток методов,
 * каждый — один POST. Параметры уходят JSON-ом, поэтому reply_markup и т.п.
 * передаются обычными массивами.
 */
class TelegramApi
{
    public function __construct(
        private readonly ?string $token = null,
        private readonly string $bot = TelegramBots::LESSON,
    ) {
    }

    public static function forBot(string $bot): self
    {
        return new self(TelegramBots::token($bot), $bot);
    }

    /** Бот воронки пробного урока. */
    public static function funnel(): self
    {
        return self::forBot(TelegramBots::LESSON);
    }

    public function bot(): string
    {
        return $this->bot;
    }

    public function isConfigured(): bool
    {
        return (string) $this->token !== '';
    }

    /**
     * @return mixed поле result ответа
     *
     * @throws TelegramApiException
     */
    public function call(string $method, array $params = []): mixed
    {
        if (! $this->isConfigured()) {
            throw new TelegramApiException(TelegramBots::env($this->bot, 'BOT_TOKEN').' не задан в .env');
        }

        try {
            $response = Http::timeout(15)
                ->connectTimeout(5)
                ->post("https://api.telegram.org/bot{$this->token}/{$method}", $params);
        } catch (\Throwable $e) {
            throw new TelegramApiException("{$method}: ".$e->getMessage());
        }

        $body = $response->json();
        if (! is_array($body) || ! ($body['ok'] ?? false)) {
            throw new TelegramApiException(
                "{$method}: ".($body['description'] ?? 'HTTP '.$response->status()),
                (int) ($body['error_code'] ?? $response->status()),
                isset($body['parameters']['retry_after']) ? (int) $body['parameters']['retry_after'] : null,
            );
        }

        return $body['result'] ?? null;
    }

    public function sendMessage(int|string $chatId, string $html, array $extra = []): array
    {
        // $extra первым: в объединении массивов побеждает левый, так шаг
        // воронки может включить превью (link_preview_options) поверх
        // выключенного по умолчанию.
        return $this->call('sendMessage', $extra + [
            'chat_id' => $chatId,
            'text' => $html,
            'parse_mode' => 'HTML',
            'link_preview_options' => ['is_disabled' => true],
        ]);
    }

    public function sendTyping(int|string $chatId): void
    {
        $this->call('sendChatAction', ['chat_id' => $chatId, 'action' => 'typing']);
    }
}
