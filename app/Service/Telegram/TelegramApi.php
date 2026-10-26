<?php

namespace App\Service\Telegram;

use Illuminate\Support\Facades\Cache;
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
    public function call(string $method, array $params = [], array $files = []): mixed
    {
        if (! $this->isConfigured()) {
            throw new TelegramApiException(TelegramBots::env($this->bot, 'BOT_TOKEN').' не задан в .env');
        }

        try {
            $request = Http::timeout(15)->connectTimeout(5);
            if ($files !== []) {
                // Файлы — только multipart, а там поля плоские: reply_markup
                // и т.п. уходят JSON-строкой.
                foreach ($files as $field => $path) {
                    $request = $request->attach($field, file_get_contents($path), basename($path));
                }
                $params = array_map(fn ($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v, $params);
            }
            $response = $request->post("https://api.telegram.org/bot{$this->token}/{$method}", $params);
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

    /**
     * Фото с подписью. $path — файл на диске: первый раз загружается, дальше
     * уходит по file_id, который Telegram вернул (у каждого бота свой).
     * Заменили файл — сменился filemtime, загрузится заново.
     */
    public function sendPhoto(int|string $chatId, string $path, string $html, array $extra = []): array
    {
        $key = "telegram.{$this->bot}.photo.".md5($path.'|'.filemtime($path));
        $params = $extra + [
            'chat_id' => $chatId,
            'caption' => $html,
            'parse_mode' => 'HTML',
        ];

        if ($fileId = Cache::get($key)) {
            return $this->call('sendPhoto', $params + ['photo' => $fileId]);
        }

        $sent = $this->call('sendPhoto', $params, ['photo' => $path]);
        if ($fileId = collect($sent['photo'] ?? [])->last()['file_id'] ?? null) { // самое большое разрешение — последним
            Cache::forever($key, $fileId);
        }

        return $sent;
    }

    public function sendTyping(int|string $chatId): void
    {
        $this->call('sendChatAction', ['chat_id' => $chatId, 'action' => 'typing']);
    }
}
