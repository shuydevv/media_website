<?php

namespace App\Service\Telegram;

use App\Models\SiteLead;
use App\Models\TelegramContact;
use App\Models\TelegramMessage;
use App\Service\Diagnostic\DiagnosticSummary;
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
 * админа в теме бот отправляет человеку от своего имени как есть
 * (relayAdminMessage).
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

    /**
     * Ссылка на тему человека в админ-группе (t.me/c/<id группы без -100>/<тема>) —
     * открывается в Telegram у участников группы.
     */
    public static function topicUrl(TelegramContact $contact): ?string
    {
        $chatId = self::adminChatId();
        if (! $chatId || ! $contact->topic_id) {
            return null;
        }

        return 'https://t.me/c/'.preg_replace('/^-100/', '', (string) $chatId).'/'.$contact->topic_id;
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
        // от нового человека создали бы ему две темы. Блокировка — строкой
        // контакта в БД, не Cache::lock: файловый кэш зависит от прав на
        // папки, и 23.10.2026 папка от root (деплой) уронила создание темы —
        // сообщение человека в группу так и не попало.
        $created = DB::transaction(function () use ($contact, $chatId) {
            TelegramContact::whereKey($contact->id)->lockForUpdate()->first();
            $contact->refresh();
            if ($contact->topic_id) {
                return false;
            }

            $topic = $this->api->call('createForumTopic', [
                'chat_id' => $chatId,
                'name' => $this->topicName($contact),
            ]);
            $contact->forceFill([
                'topic_id' => $topic['message_thread_id'],
                'topic_status' => $contact->statusEmoji(),
            ])->save();

            return true;
        });

        // Карточка — после транзакции и без исключения наружу: тема уже
        // есть, и сбой карточки не должен мешать пересылке сообщения.
        if ($created) {
            try {
                $this->sendCard($contact);
            } catch (TelegramApiException $e) {
                Log::warning('Telegram funnel: не удалось отправить карточку темы', ['contact' => $contact->id, 'error' => $e->getMessage()]);
            }
        }

        return (int) $contact->topic_id;
    }

    /**
     * Переслать сообщение человека в его тему (со звуком — на него надо
     * ответить) и отметить в telegram_messages.forwarded_at. Уже отмеченное
     * не пересылаем: его могла дослать telegram:resend-unforwarded, пока
     * задача очереди ждала повтора.
     */
    public function forward(TelegramContact $contact, int $messageId): void
    {
        $message = $contact->messages()
            ->where('kind', TelegramMessage::KIND_USER)
            ->where('telegram_message_id', $messageId)
            ->first();
        if ($message?->forwarded_at) {
            return;
        }

        $sent = $this->inTopic($contact, fn (int $chatId, int $topicId) => $this->api->call('forwardMessage', [
            'chat_id' => $chatId,
            'message_thread_id' => $topicId,
            'from_chat_id' => $contact->telegram_user_id,
            'message_id' => $messageId,
        ]));

        if ($sent) {
            $message?->forceFill(['forwarded_at' => now(), 'forward_error' => null])->save();
        }
    }

    /**
     * Запасной путь досылки, когда forwardMessage раз за разом не проходит
     * (человек удалил сообщение и т.п.): текст из telegram_messages обычным
     * сообщением в тему, со звуком.
     */
    public function forwardAsText(TelegramContact $contact, TelegramMessage $message): void
    {
        $body = $message->text !== null ? '<blockquote>'.e($message->text).'</blockquote>' : '';
        $media = $message->media_type ? "\n[{$message->media_type}] — само вложение не переслалось, смотрите в чате с ботом" : '';
        $html = '💬 <b>Сообщение от '.$message->created_at->format('d.m H:i').'</b> (переслать не удалось, текст из базы):'
            ."\n".$body.$media;

        $sent = $this->inTopic($contact, fn (int $chatId, int $topicId) => $this->api->sendMessage($chatId, $html, [
            'message_thread_id' => $topicId,
        ]));

        if ($sent) {
            $message->forceFill(['forwarded_at' => now(), 'forward_error' => null])->save();
        }
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
     * записи; если её удалили руками — заводится заново. $topicName — для
     * тем не из списка в serviceTopic (заявки с других сайтов).
     *
     * @throws TelegramApiException
     */
    public function postToService(string $key, string $html, bool $silent, ?string $topicName = null): ?array
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
            return $send($this->serviceTopic($key, $topicName));
        } catch (TelegramApiException $e) {
            if (! $e->isTopicMissing()) {
                throw $e;
            }
            DB::table('telegram_service_topics')->where('key', $key)->delete();

            return $send($this->serviceTopic($key, $topicName));
        }
    }

    /**
     * Заявка с сайта, по которой с человеком можно говорить через бота: он
     * запустил бота по ссылке со страницы «Спасибо» или записался на
     * странице урока, придя из бота ($fromSite). Тема — сразу и со звуком:
     * это заявка. В «📝 Заявки с сайта» для первого случая — отметка, что
     * отвечать можно в теме (для второго это пишет сама заявка, SendSiteLead).
     */
    public function openLeadTopic(TelegramContact $contact, SiteLead $lead, bool $fromSite = false): void
    {
        if (! $this->ensureTopic($contact)) {
            return;
        }

        $details = e($lead->name).' · '.e($lead->contactLabel()).($lead->cta_label ? ' · '.e($lead->cta_label) : '');
        $title = $fromSite ? '📝 <b>Записался на курс на сайте</b>' : '📝 <b>Пришёл из заявки с сайта</b>';
        $this->note($contact, "{$title}\n{$details}\n\nМожно отвечать прямо здесь.", false);

        if ($fromSite) {
            return;
        }

        try {
            $this->postToService('leads', '✅ '.e($lead->name).' ('.e($lead->contactLabel()).') запустил бота — отвечать можно в его теме', true);
        } catch (TelegramApiException $e) {
            Log::warning('Telegram funnel: не удалось отметить заявку', ['lead' => $lead->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Строка в «🔥 Понравилось» на хорошую оценку (без звука): самые тёплые
     * люди. Если на вопрос Александра человек не ответит, темы у него не
     * будет — а здесь он останется, со ссылкой на карточку в админке, где
     * можно «Начать диалог».
     */
    public function postLiked(TelegramContact $contact, bool $bonus): void
    {
        $code = $bonus ? FunnelLessons::bonusFor($contact->lesson) : $contact->lesson;
        $line = e(FunnelTexts::ratingLabel($contact->slot($bonus, 'rating'))).' — '.e($contact->displayName())
            .($contact->username && ! str_starts_with($contact->displayName(), '@') ? ' @'.e($contact->username) : '')
            .' · «'.e(FunnelLessons::title($code)).'»'
            .($contact->slot($bonus, 'rating_source') === 'site' ? ' · на сайте' : '')
            .' · <a href="'.e(route('admin.telegram.contacts.show', $contact)).'">карточка</a>';

        try {
            $this->postToService('liked', $line, true);
        } catch (TelegramApiException $e) {
            Log::warning('Telegram funnel: не удалось написать в «Понравилось»', ['contact' => $contact->id, 'error' => $e->getMessage()]);
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

    private function serviceTopic(string $key, ?string $topicName = null): int
    {
        $names = [
            'starts' => '📥 Старты',
            'leads' => '📝 Заявки с сайта',
            'liked' => '🔥 Понравилось',
            'diag' => '🧪 Диагностики',
            'alerts' => '⚠️ Сбои',
        ];

        $id = DB::table('telegram_service_topics')->where('key', $key)->value('topic_id');
        if ($id) {
            return (int) $id;
        }

        // Без Cache::lock (см. ensureTopic): гонку решает первичный ключ.
        // Две задачи разом создали по теме — запись остаётся за первой,
        // вторая свою тему удаляет и пишет в первую.
        $topic = $this->api->call('createForumTopic', [
            'chat_id' => self::adminChatId(),
            'name' => $names[$key] ?? $topicName ?? $key,
        ]);
        $inserted = DB::table('telegram_service_topics')->insertOrIgnore([
            'key' => $key,
            'topic_id' => $topic['message_thread_id'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($inserted) {
            return (int) $topic['message_thread_id'];
        }

        try {
            $this->api->call('deleteForumTopic', ['chat_id' => self::adminChatId(), 'message_thread_id' => $topic['message_thread_id']]);
        } catch (TelegramApiException $e) {
            Log::warning('Telegram funnel: не удалось удалить дубль служебной темы', ['key' => $key, 'error' => $e->getMessage()]);
        }

        return (int) DB::table('telegram_service_topics')->where('key', $key)->value('topic_id');
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
     * Сообщение админа в теме → человеку, от имени бота, как есть: без
     * подписи и без своего начертания. Подпись и жирный — только у
     * автосообщений «от Александра» (config telegram_funnel.signature): в
     * живом диалоге человек и так понимает, кто ему пишет. copyMessage
     * переносит текст, медиа и форматирование админа без изменений.
     */
    public function relayAdminMessage(TelegramContact $contact, array $message): void
    {
        $mediaType = self::mediaType($message);
        $text = $message['text'] ?? $message['caption'] ?? null;

        try {
            $sent = $this->api->call('copyMessage', [
                'chat_id' => $contact->telegram_user_id,
                'from_chat_id' => $message['chat']['id'],
                'message_id' => $message['message_id'],
            ]);
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
        if ($contact->bot === TelegramBots::DIAG) {
            $this->api->sendMessage(self::adminChatId(), DiagnosticSummary::card($contact), [
                'message_thread_id' => $contact->topic_id,
                'disable_notification' => true,
            ]);

            return;
        }

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
            '<i>Ответ в этой теме уйдёт человеку от бота как есть. Сообщение, начатое с //, — заметка, не отправляется.</i>',
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
     *
     * @return bool false — группа не настроена, ничего не отправлено
     */
    private function inTopic(TelegramContact $contact, callable $call): bool
    {
        $topicId = $this->ensureTopic($contact);
        if (! $topicId) {
            return false;
        }

        try {
            $call(self::adminChatId(), $topicId);
        } catch (TelegramApiException $e) {
            if (! $e->isTopicMissing()) {
                throw $e;
            }
            $contact->forceFill(['topic_id' => null, 'topic_status' => null])->save();
            $topicId = $this->ensureTopic($contact);
            if (! $topicId) {
                return false;
            }
            $call(self::adminChatId(), $topicId);
        }

        return true;
    }

    private function topicName(TelegramContact $contact): string
    {
        // 🧪 — человек из бота диагностики: группа у ботов общая.
        $name = $contact->statusEmoji().' '.($contact->bot === TelegramBots::DIAG ? '🧪 ' : '').$contact->displayName();
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
}
