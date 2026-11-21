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
 * Сообщение админа в теме чата (или реплай на уведомление в старой ленте
 * «🛒 Авито») → клиенту в чат Авито (FunnelBot::relayAvito*). Дошло — 👍 на
 * сообщении админа и чат на Авито помечается прочитанным; нет — ответ бота в
 * теме с причиной. Ушедшее записываем с from_bot: его эхо от webhook-а в тему
 * не дублируется.
 *
 * Одна попытка: при обрыве связи Авито мог сообщение уже принять, и повтор
 * прислал бы клиенту дубль. Не дошло — админ видит ошибку и отправит снова.
 */
class ReplyToAvito implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(
        public string $chatId,
        public int $userId,
        public string $text,
        public int $adminMessageId,
        public ?int $threadId,
    ) {
    }

    public function handle(): void
    {
        try {
            $avito = AvitoApi::forUser($this->userId);
            $sent = $avito->sendMessage($this->userId, $this->chatId, $this->text);
        } catch (AvitoApiException $e) {
            Log::warning('Avito: ответ не отправлен', ['chat' => $this->chatId, 'error' => $e->getMessage()]);
            $hint = $e->getCode() === 403 ? "\nПохоже, тариф или ключ API не дают отправлять сообщения." : '';
            $this->answer('⚠️ Не отправлено на Авито: <code>'.e(mb_substr($e->getMessage(), 0, 300)).'</code>'.$hint);

            return;
        }

        if (! empty($sent['id'])) {
            AvitoMessage::updateOrCreate(['message_id' => (string) $sent['id']], [
                'chat_id' => $this->chatId,
                'user_id' => $this->userId,
                'author_id' => $this->userId,
                'type' => 'text',
                'direction' => AvitoMessage::OUT,
                'from_bot' => true,
                'text' => $this->text,
                'sent_at_avito' => now(),
                'sent_at' => now(),
            ]);
        }

        try {
            $avito->markRead($this->userId, $this->chatId);
        } catch (AvitoApiException $e) {
            Log::info('Avito: не удалось пометить чат прочитанным', ['chat' => $this->chatId, 'error' => $e->getMessage()]);
        }

        try {
            TelegramApi::funnel()->call('setMessageReaction', [
                'chat_id' => AdminTopics::adminChatId(),
                'message_id' => $this->adminMessageId,
                'reaction' => [['type' => 'emoji', 'emoji' => '👍']],
            ]);
        } catch (TelegramApiException $e) {
            Log::info('Avito: не удалось поставить отметку об отправке', ['error' => $e->getMessage()]);
        }
    }

    private function answer(string $html): void
    {
        try {
            TelegramApi::funnel()->sendMessage(AdminTopics::adminChatId(), $html, array_filter([
                'message_thread_id' => $this->threadId,
                'reply_parameters' => ['message_id' => $this->adminMessageId, 'allow_sending_without_reply' => true],
            ]));
        } catch (TelegramApiException $e) {
            Log::warning('Avito: не удалось сообщить об ошибке отправки', ['error' => $e->getMessage()]);
        }
    }
}
