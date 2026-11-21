<?php

namespace App\Service\Avito;

use App\Models\AvitoChat;
use App\Service\Telegram\AdminTopics;
use App\Service\Telegram\TelegramApi;
use App\Service\Telegram\TelegramApiException;
use Illuminate\Support\Facades\Log;

/**
 * У каждого чата Авито — своя тема в админ-группе, как у людей из бота:
 * «🛒 Мария · Репетитор ЕГЭ». Первым сообщением в ней — карточка (аккаунт,
 * объявление, ссылка на чат), дальше — сообщения клиента и наши ответы из
 * Авито. Сообщение админа в теме уходит клиенту (FunnelBot::relayAvitoChat).
 * В служебной «🛒 Авито» остаётся лента новых чатов — без звука, со ссылкой
 * на тему: о самой новой теме Telegram и так уведомит.
 */
class AvitoTopics
{
    private const NAME_LIMIT = 128;

    public function __construct(private readonly TelegramApi $api)
    {
    }

    public static function make(): self
    {
        return new self(TelegramApi::funnel());
    }

    /**
     * Сообщение в тему чата; темы нет или её удалили руками — заводится
     * (тогда сначала карточка). $card — html карточки, нужен только при
     * заведении темы.
     *
     * @throws TelegramApiException
     */
    public function post(AvitoChat $chat, string $html, bool $silent, callable $card): array
    {
        $send = fn () => $this->api->sendMessage(AdminTopics::adminChatId(), $html, [
            'message_thread_id' => $this->ensureTopic($chat, $card),
            'disable_notification' => $silent,
        ]);

        try {
            return $send();
        } catch (TelegramApiException $e) {
            if (! $e->isTopicMissing()) {
                throw $e;
            }
            $chat->forceFill(['topic_id' => null])->save();

            return $send();
        }
    }

    public static function topicUrl(AvitoChat $chat): ?string
    {
        $chatId = AdminTopics::adminChatId();
        if (! $chatId || ! $chat->topic_id) {
            return null;
        }

        return 'https://t.me/c/'.preg_replace('~^-100~', '', (string) $chatId).'/'.$chat->topic_id;
    }

    /** @throws TelegramApiException */
    private function ensureTopic(AvitoChat $chat, callable $card): int
    {
        if ($chat->topic_id) {
            return $chat->topic_id;
        }

        $chatId = AdminTopics::adminChatId();
        if (! $chatId) {
            throw new TelegramApiException('TELEGRAM_FUNNEL_ADMIN_CHAT_ID не задан в .env');
        }

        $topic = $this->api->call('createForumTopic', ['chat_id' => $chatId, 'name' => $this->topicName($chat)]);
        $topicId = (int) $topic['message_thread_id'];

        // Гонка двух задач по одному чату: тема остаётся за первой записью,
        // вторая свою удаляет (как serviceTopic в AdminTopics).
        $won = AvitoChat::whereKey($chat->id)->whereNull('topic_id')->update(['topic_id' => $topicId, 'updated_at' => now()]);
        if (! $won) {
            try {
                $this->api->call('deleteForumTopic', ['chat_id' => $chatId, 'message_thread_id' => $topicId]);
            } catch (TelegramApiException $e) {
                Log::warning('Avito: не удалось удалить дубль темы чата', ['chat' => $chat->chat_id, 'error' => $e->getMessage()]);
            }
            $chat->refresh();

            return (int) $chat->topic_id;
        }

        $chat->topic_id = $topicId;
        $chat->syncOriginalAttribute('topic_id');

        $this->api->sendMessage($chatId, $card(), ['message_thread_id' => $topicId, 'disable_notification' => true]);

        try {
            $line = '🆕 '.e($chat->client_name ?? 'Новый чат')
                .(($account = $chat->account()) ? ' · '.e($account->name) : '')
                .' · <a href="'.e(self::topicUrl($chat)).'">тема</a>';
            (new AdminTopics($this->api))->postToService('avito', $line, true);
        } catch (TelegramApiException $e) {
            Log::warning('Avito: не удалось написать в ленту новых чатов', ['chat' => $chat->chat_id, 'error' => $e->getMessage()]);
        }

        return $topicId;
    }

    private function topicName(AvitoChat $chat): string
    {
        $account = $chat->account()?->name;
        $name = '🛒 '.($chat->client_name ?: 'Клиент Авито').($account ? ' · '.$account : '');

        return mb_substr($name, 0, self::NAME_LIMIT);
    }
}
