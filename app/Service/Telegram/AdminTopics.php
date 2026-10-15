<?php

namespace App\Service\Telegram;

use App\Models\SiteLead;
use App\Models\TelegramContact;
use App\Models\TelegramMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Сторона админ-группы: супергруппа с темами (TELEGRAM_FUNNEL_ADMIN_CHAT_ID),
 * одна тема на человека. Тема заводится только когда человек впервые
 * написал сам (forward): создание темы — служебное сообщение, о котором
 * Telegram уведомляет всегда, и при заведении на каждый /start группа
 * «дёргала» бы на всех, кто просто запустил бота. Что было до первого
 * сообщения (урок, открыл ли, оценка, напоминание) — сводкой в карточке
 * темы; после — копии автосообщений бота и события, без звука. Сообщение
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

    /**
     * Служебная строка в тему: копия автосообщения, «открыл урок» и т.п.
     * Темы ещё нет (человек не писал) — ничего не делаем: это попадёт в
     * сводку карточки, когда тема появится.
     */
    public function note(TelegramContact $contact, string $html, bool $silent = true): void
    {
        if (! $contact->topic_id) {
            return;
        }

        try {
            $this->inTopic($contact, fn (int $chatId, int $topicId) => $this->api->sendMessage($chatId, $html, [
                'message_thread_id' => $topicId,
                'disable_notification' => $silent,
            ]));
        } catch (TelegramApiException $e) {
            Log::warning('Telegram funnel: не удалось написать в тему', ['contact' => $contact->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Сообщение в служебную тему группы (не тему человека): лента стартов
     * без звука, заявки с сайта — со звуком. Тема заводится при первой
     * записи; если её удалили руками — заводится заново.
     *
     * @throws TelegramApiException
     */
    public function postToService(string $key, string $html, bool $silent): ?array
    {
        $chatId = self::adminChatId();
        if (! $chatId) {
            throw new TelegramApiException('TELEGRAM_FUNNEL_ADMIN_CHAT_ID не задан в .env');
        }

        $send = fn (int $topicId) => $this->api->sendMessage($chatId, $html, [
            'message_thread_id' => $topicId,
            'disable_notification' => $silent,
        ]);

        try {
            return $send($this->serviceTopic($key));
        } catch (TelegramApiException $e) {
            if (! $e->isTopicMissing()) {
                throw $e;
            }
            DB::table('telegram_service_topics')->where('key', $key)->delete();

            return $send($this->serviceTopic($key));
        }
    }

    /**
     * Человек из заявки на сайте запустил бота: заводим ему тему сразу (с
     * уведомлением — это заявка) и отмечаем в «📝 Заявки с сайта», что с
     * ним теперь можно говорить через бота.
     */
    public function openLeadTopic(TelegramContact $contact, SiteLead $lead): void
    {
        if (! $this->ensureTopic($contact)) {
            return;
        }

        $details = e($lead->name).' · '.e($lead->contactLabel()).($lead->cta_label ? ' · '.e($lead->cta_label) : '');
        $this->note($contact, "📝 <b>Пришёл из заявки с сайта</b>
{$details}

Можно отвечать прямо здесь.", false);

        try {
            $this->postToService('leads', '✅ '.e($lead->name).' ('.e($lead->contactLabel()).') запустил бота — отвечать можно в его теме', true);
        } catch (TelegramApiException $e) {
            Log::warning('Telegram funnel: не удалось отметить заявку', ['lead' => $lead->id, 'error' => $e->getMessage()]);
        }
    }

    /** Строка в «📥 Старты» на каждый новый запуск бота — без звука. */
    public function postStart(TelegramContact $contact): void
    {
        $line = '🆕 '.e($contact->displayName())
            .($contact->username && ! str_starts_with($contact->displayName(), '@') ? ' @'.e($contact->username) : '')
            .' · '.($contact->source ? '<code>'.e($contact->source).'</code>' : 'без метки')
            .' · «'.e(FunnelLessons::title($contact->lesson)).'»';

        try {
            $this->postToService('starts', $line, true);
        } catch (TelegramApiException $e) {
            Log::warning('Telegram funnel: не удалось написать в ленту стартов', ['contact' => $contact->id, 'error' => $e->getMessage()]);
        }
    }

    private function serviceTopic(string $key): int
    {
        $names = [
            'starts' => '📥 Старты',
            'leads' => '📝 Заявки с сайта',
        ];

        return Cache::lock("telegram-service-topic-{$key}", 20)->block(15, function () use ($key, $names) {
            $id = DB::table('telegram_service_topics')->where('key', $key)->value('topic_id');
            if ($id) {
                return (int) $id;
            }

            $topic = $this->api->call('createForumTopic', [
                'chat_id' => self::adminChatId(),
                'name' => $names[$key] ?? $key,
            ]);
            DB::table('telegram_service_topics')->insert([
                'key' => $key,
                'topic_id' => $topic['message_thread_id'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return (int) $topic['message_thread_id'];
        });
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
        $prefix = "💬  {$signature}\n";
        $prefixLen = self::utf16Length($prefix);
        // Курсивом — как подпись автосообщений (config telegram_funnel.signature).
        $signatureEntity = [
            'type' => 'italic',
            'offset' => self::utf16Length('💬  '),
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
        $at = fn ($time) => $time ? $time->format('d.m H:i') : null;
        // Строка урока: открыл ли, сколько посмотрел, оценка и где поставлена.
        $slot = function (bool $bonus) use ($contact, $at) {
            $code = $bonus ? FunnelLessons::bonusFor($contact->lesson) : $contact->lesson;
            $line = ($bonus ? 'Бонус «' : '«').e(FunnelLessons::title($code)).'»: ';
            if ($contact->slot($bonus, 'opened_at') === null) {
                return $line.'не открывал';
            }
            $line .= 'открыл '.$at($contact->slot($bonus, 'opened_at'));
            if ((int) $contact->slot($bonus, 'progress') > 0) {
                $line .= ' · посмотрел '.(int) $contact->slot($bonus, 'progress').'%';
            }
            if ($rating = $contact->slot($bonus, 'rating')) {
                $line .= ' · '.e(FunnelTexts::ratingLabel($rating)).($contact->slot($bonus, 'rating_source') === 'site' ? ' (на сайте)' : '');
            }

            return $line;
        };

        $lines = [
            '<b>Новый диалог</b>',
            '<a href="tg://user?id='.$contact->telegram_user_id.'">'.e($contact->displayName()).'</a>'
                .($contact->username ? ' · @'.e($contact->username) : ''),
            'Метка: '.($contact->source ? '<code>'.e($contact->source).'</code>' : 'без метки'),
            'Старт: '.$contact->started_at->format('d.m.Y H:i'),
            '',
            $slot(false),
            $slot(true),
            'Напоминание: '.($at($contact->reminder_sent_at) ? 'отправлено '.$at($contact->reminder_sent_at) : 'не отправлялось'),
            '',
            '<i>Ответ в этой теме уйдёт человеку от бота с подписью «Александр Полтавский». Сообщение, начатое с //, — заметка, не отправляется.</i>',
        ];

        $this->api->sendMessage(self::adminChatId(), implode('
', $lines), [
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
