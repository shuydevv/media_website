<?php

namespace App\Jobs\Avito;

use App\Models\AvitoChat;
use App\Models\AvitoMessage;
use App\Service\Avito\AvitoApi;
use App\Service\Avito\AvitoApiException;
use App\Service\Avito\AvitoTopics;
use App\Service\Telegram\TelegramApiException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Сообщение Авито → тема этого чата в админ-группе (AvitoTopics). Входящее —
 * со звуком (системное — без), наш ответ из самого Авито — без звука, с
 * пометкой. Ответ, ушедший из Telegram (from_bot), не дублируем: задача для
 * исходящих ставится с задержкой (WebhookController), чтобы ReplyToAvito
 * успел пометить своё сообщение.
 *
 * Имя клиента и объявление берём из чата в API Авито; не ответил — шлём без
 * них, уведомление важнее. Повторы — у очереди: sent_at = null значит, что в
 * группу ещё не дошло.
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
        if ($message->from_bot) {
            $message->forceFill(['sent_at' => now()])->save();

            return;
        }

        $chat = AvitoChat::firstOrCreate(['chat_id' => $message->chat_id], ['user_id' => $message->user_id]);

        $info = [];
        if (! $chat->topic_id || ! $chat->client_name) {
            try {
                $info = AvitoApi::forUser($message->user_id)->chat($message->user_id, $message->chat_id);
            } catch (AvitoApiException $e) {
                Log::warning('Avito: не удалось получить чат', ['chat' => $message->chat_id, 'error' => $e->getMessage()]);
            }
            // Клиент — автор входящего; иначе (наш ответ, системное) — любой не мы.
            $users = collect($info['users'] ?? []);
            $client = ($message->direction === AvitoMessage::IN ? $users->firstWhere('id', $message->author_id) : null)
                ?? $users->first(fn ($u) => (int) ($u['id'] ?? 0) !== $message->user_id);
            $client = $client['name'] ?? null;
            if ($client && ! $chat->client_name) {
                $chat->forceFill(['client_name' => mb_substr($client, 0, 255)])->save();
            }
        }

        $system = $message->type === 'system' || $message->author_id === 0;
        $out = $message->direction === AvitoMessage::OUT;

        try {
            $sent = AvitoTopics::make()->post($chat, $this->body($message, $system, $out), $system || $out, fn () => $this->card($chat, $message, $info));
        } catch (TelegramApiException $e) {
            if ($e->isRateLimited() && $e->retryAfter) {
                $this->release($e->retryAfter + 1);

                return;
            }
            throw $e;
        }

        $message->forceFill(['sent_at' => now(), 'telegram_message_id' => $sent['message_id'] ?? null])->save();
    }

    private function body(AvitoMessage $message, bool $system, bool $out): string
    {
        $text = (string) $message->text;
        $text = mb_strlen($text) > self::TEXT_LIMIT ? mb_substr($text, 0, self::TEXT_LIMIT).'…' : $text;

        if ($out) {
            return '↩️ <i>ответ из Авито</i>'.($text !== '' ? "\n".e($text) : '');
        }
        if ($system) {
            return 'ℹ️ <i>'.e($text !== '' ? $text : 'системное сообщение').'</i>';
        }

        return $text !== '' ? e($text) : '[сообщение]';
    }

    /** Первое сообщение темы: аккаунт, клиент, объявление, ссылка на чат и как отвечать. */
    private function card(AvitoChat $chat, AvitoMessage $message, array $info): string
    {
        $account = $chat->account()?->name;
        $item = ($info['context']['type'] ?? null) === 'item' ? ($info['context']['value'] ?? []) : [];

        $lines = ['🛒 <b>Авито'.($account ? ' · '.e($account) : '').'</b>'];
        if ($chat->client_name) {
            $lines[] = '👤 '.e($chat->client_name);
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
        $lines[] = '<a href="'.e($message->chatUrl()).'">Открыть чат на Авито</a>';
        $lines[] = '';
        $lines[] = 'Пишите прямо в эту тему — сообщение уйдёт клиенту. Заметка для себя — с <code>//</code>.';

        return implode("\n", $lines);
    }
}
