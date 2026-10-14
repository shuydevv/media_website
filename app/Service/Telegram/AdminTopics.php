<?php

namespace App\Service\Telegram;

use App\Models\TelegramContact;
use App\Models\TelegramMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Сторона админ-группы: супергруппа с темами (TELEGRAM_FUNNEL_ADMIN_CHAT_ID),
 * одна тема на человека. Туда пересылается всё, что пишет человек, и
 * копируются автосообщения бота — для контекста, без звука. Сообщение
 * админа в теме бот отправляет человеку от своего имени с подписью
 * Александра (relayAdminMessage).
 *
 * Сбой на этой стороне (группа не настроена, Telegram недоступен) не должен
 * ронять воронку для самого человека — поэтому note()/refreshStatus() глотают
 * ошибки в лог, а бросают наружу только там, где повтор задачи безопасен
 * (forward — одна пересылка на задачу).
 */
class AdminTopics
{
    public function __construct(private readonly TelegramApi $api)
    {
    }

    public static function adminChatId(): ?int
    {
        $id = config('services.telegram_funnel.admin_chat_id');

        return $id ? (int) $id : null;
    }

    public static function isAdminChat(int|string $chatId): bool
    {
        return self::adminChatId() !== null && (int) $chatId === self::adminChatId();
    }

    /** @return int|null id темы или null, если группа не настроена */
    public function ensureTopic(TelegramContact $contact): ?int
    {
        if ($contact->topic_id) {
            return (int) $contact->topic_id;
        }
        $chatId = self::adminChatId();
        if (! $chatId) {
            return null;
        }

        // Сейчас воркер очереди telegram один и задачи идут по порядку, но
        // если их станет несколько — без блокировки два сообщения подряд
        // от нового человека создали бы ему две темы.
        return Cache::lock("telegram-topic-{$contact->id}", 20)->block(15, function () use ($contact, $chatId) {
            $contact->refresh();
            if ($contact->topic_id) {
                return (int) $contact->topic_id;
            }

            $topic = $this->api->call('createForumTopic', [
                'chat_id' => $chatId,
                'name' => $this->topicName($contact),
            ]);
            $contact->forceFill([
                'topic_id' => $topic['message_thread_id'],
                'topic_status' => $contact->statusEmoji(),
            ])->save();

            $this->sendCard($contact);

            return (int) $contact->topic_id;
        });
    }

    /** Переслать сообщение человека в его тему (со звуком — на него надо ответить). */
    public function forward(TelegramContact $contact, int $messageId): void
    {
        $this->inTopic($contact, fn (int $chatId, int $topicId) => $this->api->call('forwardMessage', [
            'chat_id' => $chatId,
            'message_thread_id' => $topicId,
            'from_chat_id' => $contact->telegram_user_id,
            'message_id' => $messageId,
        ]));
    }

    /** Служебная строка в тему: копия автосообщения, «открыл урок» и т.п. */
    public function note(TelegramContact $contact, string $html, bool $silent = true): void
    {
        try {
            $this->inTopic($contact, fn (int $chatId, int $topicId) => $this->api->sendMessage($chatId, $html, [
                'message_thread_id' => $topicId,
                'disable_notification' => $silent,
            ]));
        } catch (TelegramApiException $e) {
            Log::warning('Telegram funnel: не удалось написать в тему', ['contact' => $contact->id, 'error' => $e->getMessage()]);
        }
    }

    /** Переименовать тему, если сменился эмодзи-статус (💬 — ждёт ответа и т.п.). */
    public function refreshStatus(TelegramContact $contact): void
    {
        if (! $contact->topic_id || ! self::adminChatId() || $contact->topic_status === $contact->statusEmoji()) {
            return;
        }

        try {
            $this->api->call('editForumTopic', [
                'chat_id' => self::adminChatId(),
                'message_thread_id' => $contact->topic_id,
                'name' => $this->topicName($contact),
            ]);
            $contact->forceFill(['topic_status' => $contact->statusEmoji()])->save();
        } catch (TelegramApiException $e) {
            // «TOPIC_NOT_MODIFIED» — название уже такое, это не ошибка.
            if (str_contains($e->getMessage(), 'NOT_MODIFIED')) {
                $contact->forceFill(['topic_status' => $contact->statusEmoji()])->save();

                return;
            }
            Log::warning('Telegram funnel: не удалось переименовать тему', ['contact' => $contact->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Сообщение админа в теме → человеку, от имени бота, с подписью
     * Александра первой строкой. Форматирование и ссылки админа сохраняются:
     * подпись добавляется обычным текстом с bold-entity, а entities самого
     * сообщения сдвигаются на её длину (в UTF-16 — так их считает Telegram).
     */
    public function relayAdminMessage(TelegramContact $contact, array $message): void
    {
        $signature = 'Александр Полтавский:';
        $prefix = "🙋🏼‍♂️ {$signature}\n";
        $prefixLen = self::utf16Length($prefix);
        $signatureEntity = [
            'type' => 'bold',
            'offset' => self::utf16Length('🙋🏼‍♂️ '),
            'length' => self::utf16Length($signature),
        ];
        $shift = fn (array $entities) => array_map(fn ($en) => ['offset' => $en['offset'] + $prefixLen] + $en, $entities);

        $mediaType = self::mediaType($message);
        $text = $message['text'] ?? $message['caption'] ?? null;

        try {
            if (isset($message['text'])) {
                $sent = $this->api->call('sendMessage', [
                    'chat_id' => $contact->telegram_user_id,
                    'text' => $prefix.$message['text'],
                    'entities' => array_merge([$signatureEntity], $shift($message['entities'] ?? [])),
                    'link_preview_options' => ['is_disabled' => true],
                ]);
            } elseif (in_array($mediaType, ['photo', 'video', 'document', 'audio', 'voice', 'animation'], true)) {
                $sent = $this->api->call('copyMessage', [
                    'chat_id' => $contact->telegram_user_id,
                    'from_chat_id' => $message['chat']['id'],
                    'message_id' => $message['message_id'],
                    'caption' => $prefix.($message['caption'] ?? ''),
                    'caption_entities' => array_merge([$signatureEntity], $shift($message['caption_entities'] ?? [])),
                ]);
            } else {
                // Стикер, кружок, геопозиция — подпись к ним не прикрепить,
                // уходят как есть.
                $sent = $this->api->call('copyMessage', [
                    'chat_id' => $contact->telegram_user_id,
                    'from_chat_id' => $message['chat']['id'],
                    'message_id' => $message['message_id'],
                ]);
            }
        } catch (TelegramApiException $e) {
            if ($e->isBlockedByUser()) {
                $contact->forceFill(['blocked_at' => now()])->save();
                $this->note($contact, '⛔ Не доставлено: человек заблокировал бота.', false);
                $this->refreshStatus($contact);

                return;
            }
            throw $e;
        }

        $now = now();
        $contact->forceFill([
            'first_admin_message_at' => $contact->first_admin_message_at ?? $now,
            'last_admin_message_at' => $now,
        ])->save();

        TelegramMessage::create([
            'telegram_contact_id' => $contact->id,
            'direction' => TelegramMessage::DIRECTION_OUT,
            'kind' => TelegramMessage::KIND_ADMIN,
            'text' => $text,
            'media_type' => $mediaType,
            'file_id' => self::fileId($message),
            'telegram_message_id' => $sent['message_id'] ?? null,
        ]);

        $this->refreshStatus($contact);
    }

    private function sendCard(TelegramContact $contact): void
    {
        $lines = [
            '🆕 <b>Новый контакт</b>',
            '<a href="tg://user?id='.$contact->telegram_user_id.'">'.e($contact->displayName()).'</a>'
                .($contact->username ? ' · @'.e($contact->username) : ''),
            'Метка: '.($contact->source ? '<code>'.e($contact->source).'</code>' : 'без метки'),
            'Урок: «'.e(FunnelLessons::title($contact->lesson)).'», бонус: «'.e(FunnelLessons::title(FunnelLessons::bonusFor($contact->lesson))).'»',
            'Старт: '.$contact->started_at->format('d.m.Y H:i'),
            '',
            '<i>Ответ в этой теме уйдёт человеку от бота с подписью «Александр Полтавский». Сообщение, начатое с //, — заметка, не отправляется.</i>',
        ];

        $this->api->sendMessage(self::adminChatId(), implode("\n", $lines), [
            'message_thread_id' => $contact->topic_id,
            'disable_notification' => true,
        ]);
    }

    /**
     * Вызов в тему человека; если тему удалили руками — создаём новую и
     * повторяем один раз.
     */
    private function inTopic(TelegramContact $contact, callable $call): void
    {
        $topicId = $this->ensureTopic($contact);
        if (! $topicId) {
            return;
        }

        try {
            $call(self::adminChatId(), $topicId);
        } catch (TelegramApiException $e) {
            if (! $e->isTopicMissing()) {
                throw $e;
            }
            $contact->forceFill(['topic_id' => null, 'topic_status' => null])->save();
            $topicId = $this->ensureTopic($contact);
            if ($topicId) {
                $call(self::adminChatId(), $topicId);
            }
        }
    }

    private function topicName(TelegramContact $contact): string
    {
        $name = $contact->statusEmoji().' '.$contact->displayName();
        if ($contact->username && ! str_starts_with($contact->displayName(), '@')) {
            $name .= ' @'.$contact->username;
        }

        return mb_substr($name, 0, 128);
    }

    public static function mediaType(array $message): ?string
    {
        foreach (['photo', 'video', 'video_note', 'voice', 'audio', 'document', 'animation', 'sticker', 'location', 'contact', 'poll'] as $type) {
            if (isset($message[$type])) {
                return $type;
            }
        }

        return null;
    }

    public static function fileId(array $message): ?string
    {
        if (isset($message['photo'])) {
            return end($message['photo'])['file_id'] ?? null; // самое большое разрешение — последним
        }
        foreach (['video', 'video_note', 'voice', 'audio', 'document', 'animation', 'sticker'] as $type) {
            if (isset($message[$type]['file_id'])) {
                return $message[$type]['file_id'];
            }
        }

        return null;
    }

    private static function utf16Length(string $s): int
    {
        return intdiv(strlen(mb_convert_encoding($s, 'UTF-16LE', 'UTF-8')), 2);
    }
}
