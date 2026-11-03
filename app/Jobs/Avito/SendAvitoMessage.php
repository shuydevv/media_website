<?php

namespace App\Jobs\Avito;

use App\Models\AvitoMessage;
use App\Service\Avito\AvitoApi;
use App\Service\Avito\AvitoApiException;
use App\Service\Telegram\AdminTopics;
use App\Service\Telegram\TelegramApi;
use App\Service\Telegram\TelegramApiException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Входящее сообщение Авито → тема «🛒 Авито» в админ-группе бота воронки,
 * со звуком (системные — без). Имя собеседника и объявление берём из чата
 * в API Авито; не ответил — шлём без них, уведомление важнее.
 * Повторы — у очереди: sent_at = null значит, что в группу ещё не дошло.
 */
class SendAvitoMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    /** Сколько символов текста показывать (лимит сообщения Telegram — 4096). */
    private const TEXT_LIMIT = 3000;

    public function __construct(public int $messageId)
    {
    }

    public function backoff(): array
    {
        return [10, 60, 300, 1800];
    }

    public function handle(): void
    {
        $message = AvitoMessage::find($this->messageId);
        if (! $message || $message->sent_at) {
            return;
        }

        $chat = [];
        try {
            $chat = (new AvitoApi())->chat($message->user_id, $message->chat_id);
        } catch (AvitoApiException $e) {
            Log::warning('Avito: не удалось получить чат', ['chat' => $message->chat_id, 'error' => $e->getMessage()]);
        }

        $system = $message->type === 'system' || $message->author_id === 0;
        $newChat = ! $system && $message->opensChat()
            // Чат, начатый до подключения webhook-а, новым не считаем.
            && (! isset($chat['created']) || ! $message->sent_at_avito || $chat['created'] >= $message->sent_at_avito->timestamp - 600);

        $author = collect($chat['users'] ?? [])->firstWhere('id', $message->author_id)['name'] ?? null;
        $item = ($chat['context']['type'] ?? null) === 'item' ? ($chat['context']['value'] ?? []) : [];

        $lines = [$newChat ? '🛒 <b>Авито · новый чат</b>' : ($system ? '🛒 Авито · системное' : '🛒 <b>Авито</b>')];
        if ($author) {
            $lines[] = '👤 '.e($author);
        }
        if (! empty($item['title'])) {
            $title = e($item['title']);
            $url = (string) ($item['url'] ?? '');
            if ($url !== '' && ! str_starts_with($url, 'http')) {
                $url = 'https://www.avito.ru'.$url;
            }
            $lines[] = '📦 '.($url !== '' ? '<a href="'.e($url).'">'.$title.'</a>' : $title)
                .(! empty($item['price_string']) ? ' · '.e($item['price_string']) : '');
        }
        if ($message->text !== null && $message->text !== '') {
            $text = mb_strlen($message->text) > self::TEXT_LIMIT ? mb_substr($message->text, 0, self::TEXT_LIMIT).'…' : $message->text;
            $lines[] = '';
            $lines[] = e($text);
        }
        $lines[] = '';
        $lines[] = '<a href="'.e($message->chatUrl()).'">Открыть чат на Авито</a>';

        try {
            (new AdminTopics(TelegramApi::funnel()))->postToService('avito', implode("\n", $lines), $system);
        } catch (TelegramApiException $e) {
            if ($e->isRateLimited() && $e->retryAfter) {
                $this->release($e->retryAfter + 1);

                return;
            }
            throw $e;
        }

        $message->forceFill(['sent_at' => now()])->save();
    }
}
