<?php

namespace App\Service\Telegram;

use App\Jobs\Telegram\RunFunnelStep;
use App\Models\TelegramContact;
use App\Models\TelegramMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Воронка пробного урока (путь пользователя — «TG инструкция.md»):
 *
 *   /start <урок>_<метка> → приветствие с уроком и кнопками 👍 👎,
 *                            через ~5 с бонусный (второй) урок
 *   👍 → «Рад, что понравился», через ~20 с вопрос от Александра
 *   👎 → «Что не понравилось?», дальше бот сам не пишет
 *   ничего → через 22–26 ч напоминание от Александра (10:00–21:00 МСК)
 *
 * Как только человек написал сам или ему ответил админ — автосообщения
 * больше не уходят (TelegramContact::automationAllowed()), дальше ведёт
 * живой человек в теме админ-группы (AdminTopics).
 *
 * handleUpdate() только пишет в БД и раскладывает работу по задачам
 * RunFunnelStep — по одной отправке на задачу. Так повтор упавшей задачи
 * не шлёт человеку дубль того, что уже успело уйти.
 */
class FunnelBot
{
    public function __construct(
        private readonly TelegramApi $api,
        private readonly AdminTopics $topics,
    ) {
    }

    public static function make(): self
    {
        $api = TelegramApi::funnel();

        return new self($api, new AdminTopics($api));
    }

    public function handleUpdate(array $update): void
    {
        if (isset($update['callback_query'])) {
            $this->handleCallback($update['callback_query']);

            return;
        }

        if (isset($update['my_chat_member'])) {
            $this->handleChatMember($update['my_chat_member']);

            return;
        }

        $message = $update['message'] ?? null;
        if (! $message || ! isset($message['chat']['id'])) {
            return; // edited_message и прочее пока не обрабатываем
        }

        if (($message['chat']['type'] ?? null) === 'private') {
            $this->handlePrivateMessage($message);

            return;
        }

        if (AdminTopics::isAdminChat($message['chat']['id'])) {
            $this->handleAdminChatMessage($message);

            return;
        }

        // Любая другая группа: только подсказка id — чтобы узнать
        // TELEGRAM_FUNNEL_ADMIN_CHAT_ID, достаточно написать /chatid в группе.
        if (str_starts_with($message['text'] ?? '', '/chatid')) {
            $this->api->sendMessage($message['chat']['id'], 'chat_id этой группы: <code>'.$message['chat']['id'].'</code>');
        }
    }

    /**
     * Одна отправка из RunFunnelStep. Условия проверяются здесь, в момент
     * отправки, а не при постановке в очередь — это и есть «отмена таймера».
     */
    public function runStep(TelegramContact $contact, string $step, array $payload = []): void
    {
        match ($step) {
            'welcome' => $this->sendWelcome($contact),
            'bonus' => $this->sendBonus($contact),
            'like_auto', 'dislike_auto' => $this->sendAuto($contact, $step, FunnelTexts::render($step)),
            'like_human' => $this->sendLikeHuman($contact),
            'reminder' => $this->sendReminder($contact),
            'remove_rating_buttons' => $this->removeRatingButtons($contact),
            'topic' => $this->topics->ensureTopic($contact),
            'forward' => $this->topics->forward($contact, (int) $payload['message_id']),
            'note' => $this->topics->note($contact, $payload['html'], $payload['silent'] ?? true),
            'admin_reply' => $this->topics->relayAdminMessage($contact, $payload['message']),
            'refresh_status' => $this->topics->refreshStatus($contact),
            default => Log::warning("Telegram funnel: неизвестный шаг {$step}"),
        };
    }

    private function handlePrivateMessage(array $message): void
    {
        $from = $message['from'] ?? null;
        if (! $from || ($from['is_bot'] ?? false)) {
            return;
        }

        $text = $message['text'] ?? null;
        if ($text !== null && preg_match('~^/start(?:@\w+)?(?:\s+(\S+))?~', $text, $m)) {
            $this->handleStart($from, $m[1] ?? null);

            return;
        }

        // Пишет без /start — например, запускал бота до того, как появилась
        // эта таблица. Заводим контакт молча, без приветствия: человек уже
        // разговаривает, дальше — живой ответ.
        $contact = TelegramContact::where('telegram_user_id', $from['id'])->first()
            ?? $this->createContact($from, config('telegram_funnel.default_lesson'), null);

        $now = now();
        $contact->forceFill([
            'first_user_message_at' => $contact->first_user_message_at ?? $now,
            'last_user_message_at' => $now,
            'user_messages_count' => $contact->user_messages_count + 1,
            'blocked_at' => null,
        ])->save();

        TelegramMessage::create([
            'telegram_contact_id' => $contact->id,
            'direction' => TelegramMessage::DIRECTION_IN,
            'kind' => TelegramMessage::KIND_USER,
            'text' => $message['text'] ?? $message['caption'] ?? null,
            'media_type' => AdminTopics::mediaType($message),
            'file_id' => AdminTopics::fileId($message),
            'telegram_message_id' => $message['message_id'],
        ]);

        $this->dispatch($contact, 'forward', ['message_id' => $message['message_id']]);
        $this->dispatch($contact, 'refresh_status');
    }

    private function handleStart(array $from, ?string $param): void
    {
        $parsed = StartParam::parse($param, FunnelLessons::codes(), config('telegram_funnel.default_lesson'));
        $contact = TelegramContact::where('telegram_user_id', $from['id'])->first();

        if (! $contact) {
            $contact = $this->createContact($from, $parsed['lesson'], $parsed['source']);
        } else {
            // Метка и урок — по первому касанию (см. миграцию), обновляем
            // только имя и время. Повторное приветствие — с тем же уроком.
            $contact->forceFill([
                'username' => $from['username'] ?? null,
                'first_name' => $from['first_name'] ?? null,
                'last_name' => $from['last_name'] ?? null,
                'last_start_at' => now(),
                'blocked_at' => null,
            ])->save();
            $this->dispatch($contact, 'note', [
                'html' => '🔁 Повторный /start'.($param ? ' (<code>'.e($param).'</code>)' : ''),
            ]);
        }

        $this->dispatch($contact, 'welcome');
        $this->dispatch($contact, 'bonus', [], config('telegram_funnel.timing.bonus_delay_seconds'));
    }

    private function createContact(array $from, string $lesson, ?string $source): TelegramContact
    {
        $now = now();
        $contact = TelegramContact::create([
            'telegram_user_id' => $from['id'],
            'username' => $from['username'] ?? null,
            'first_name' => $from['first_name'] ?? null,
            'last_name' => $from['last_name'] ?? null,
            'source' => $source,
            'lesson' => $lesson,
            'link_token' => Str::random(16),
            'started_at' => $now,
            'last_start_at' => $now,
            'reminder_due_at' => ReminderSchedule::randomFor($now),
        ]);

        // Тему заводим сразу (с карточкой: имя, метка, урок), а не по первому
        // сообщению — чтобы и молчуны были видны в группе.
        $this->dispatch($contact, 'topic');

        return $contact;
    }

    private function handleCallback(array $callback): void
    {
        $data = $callback['data'] ?? '';
        $contact = TelegramContact::where('telegram_user_id', $callback['from']['id'] ?? 0)->first();

        if (! $contact || ! in_array($data, ['rate:up', 'rate:down'], true)) {
            $this->answerCallback($callback['id']);

            return;
        }

        if ($contact->rating !== null) {
            $this->answerCallback($callback['id'], 'Оценка уже есть — спасибо!');

            return;
        }

        $rating = $data === 'rate:up' ? TelegramContact::RATING_UP : TelegramContact::RATING_DOWN;
        $contact->forceFill([
            'rating' => $rating,
            'rated_at' => now(),
            'rating_message_id' => $callback['message']['message_id'] ?? $contact->rating_message_id,
            'reminder_due_at' => null,
        ])->save();

        $this->answerCallback($callback['id']);

        $this->dispatch($contact, 'remove_rating_buttons');
        $this->dispatch($contact, 'note', [
            'html' => $rating === TelegramContact::RATING_UP ? '👍 Оценил урок' : '👎 Не понравился урок',
        ]);
        $this->dispatch($contact, 'refresh_status');

        if ($rating === TelegramContact::RATING_UP) {
            $this->dispatch($contact, 'like_auto');
            $this->dispatch($contact, 'like_human', [], config('telegram_funnel.timing.like_human_delay_seconds'));
        } else {
            $this->dispatch($contact, 'dislike_auto');
        }
    }

    private function handleChatMember(array $update): void
    {
        if (($update['chat']['type'] ?? null) !== 'private') {
            return;
        }
        $contact = TelegramContact::where('telegram_user_id', $update['from']['id'] ?? 0)->first();
        if (! $contact) {
            return;
        }

        $status = $update['new_chat_member']['status'] ?? null;
        if ($status === 'kicked') {
            $contact->forceFill(['blocked_at' => now()])->save();
            $this->dispatch($contact, 'note', ['html' => '⛔ Заблокировал бота']);
        } elseif ($status === 'member' && $contact->blocked_at) {
            $contact->forceFill(['blocked_at' => null])->save();
            $this->dispatch($contact, 'note', ['html' => '✅ Разблокировал бота']);
        }
        $this->dispatch($contact, 'refresh_status');
    }

    private function handleAdminChatMessage(array $message): void
    {
        $threadId = $message['message_thread_id'] ?? null;
        $from = $message['from'] ?? [];

        // Только обычные сообщения людей внутри темы: служебные (тема
        // создана/переименована), «General» и свои сообщения бота — мимо.
        if (! $threadId || ! ($message['is_topic_message'] ?? false) || ($from['is_bot'] ?? false)
            || isset($message['forum_topic_created']) || isset($message['forum_topic_edited'])) {
            return;
        }

        $body = $message['text'] ?? $message['caption'] ?? '';
        if (str_starts_with(ltrim($body), '//')) {
            return; // заметка для себя
        }

        $contact = TelegramContact::where('topic_id', $threadId)->first();
        if (! $contact) {
            return;
        }

        $this->dispatch($contact, 'admin_reply', ['message' => $message]);
    }

    private function sendWelcome(TelegramContact $contact): void
    {
        $keyboard = [[$this->lessonButton($contact, $contact->lesson)]];
        if ($contact->rating === null) {
            $keyboard[] = [
                ['text' => '👍', 'callback_data' => 'rate:up'],
                ['text' => '👎', 'callback_data' => 'rate:down'],
            ];
        }

        $sent = $this->sendAuto($contact, 'welcome', FunnelTexts::render('welcome', $this->vars($contact)), [
            'reply_markup' => ['inline_keyboard' => $keyboard],
        ]);

        if ($sent && $contact->rating === null) {
            $contact->forceFill(['rating_message_id' => $sent['message_id']])->save();
        }
    }

    private function sendBonus(TelegramContact $contact): void
    {
        $bonus = FunnelLessons::bonusFor($contact->lesson);
        // Какой текст — зависит от того, «сложнее» бонус или «проще»: первый
        // урок в config/telegram_funnel.lessons — лёгкий.
        $key = $contact->lesson === FunnelLessons::codes()[0] ? 'bonus_harder' : 'bonus_easier';

        $this->sendAuto($contact, $key, FunnelTexts::render($key, $this->vars($contact)), [
            'reply_markup' => ['inline_keyboard' => [[$this->lessonButton($contact, $bonus)]]],
        ]);
    }

    private function sendLikeHuman(TelegramContact $contact): void
    {
        // Если за эти ~20 секунд человек уже сам что-то написал или ему
        // ответил админ — вопрос «что понравилось?» был бы невпопад.
        $repliedSince = fn ($at) => $at !== null && $contact->rated_at !== null && $at->gte($contact->rated_at);
        if ($contact->blocked_at || $contact->rating !== TelegramContact::RATING_UP
            || $repliedSince($contact->last_user_message_at) || $repliedSince($contact->last_admin_message_at)) {
            return;
        }

        $this->sendHuman($contact, 'like_human');
    }

    private function sendReminder(TelegramContact $contact): void
    {
        if (! $contact->automationAllowed() || $contact->reminder_sent_at !== null) {
            return;
        }

        $this->sendHuman($contact, 'reminder');
        $contact->forceFill(['reminder_sent_at' => now()])->save();
    }

    /** «Человеческое» сообщение: сначала «печатает…» 2–3 с, потом текст с подписью. */
    private function sendHuman(TelegramContact $contact, string $key): void
    {
        $t = config('telegram_funnel.timing');
        try {
            $this->api->sendTyping($contact->telegram_user_id);
            sleep(random_int($t['typing_seconds_min'], $t['typing_seconds_max']));
        } catch (TelegramApiException) {
            // без «печатает…» сообщение всё равно уйдёт (или упадёт ниже с той же ошибкой)
        }

        $this->sendAuto($contact, $key, FunnelTexts::render($key, $this->vars($contact)));
    }

    /**
     * Отправка автосообщения человеку + запись в историю + копия в тему.
     *
     * @return array|null отправленное сообщение или null, если бот заблокирован
     */
    private function sendAuto(TelegramContact $contact, string $step, string $html, array $extra = []): ?array
    {
        if ($contact->blocked_at) {
            return null;
        }

        try {
            $sent = $this->api->sendMessage($contact->telegram_user_id, $html, $extra);
        } catch (TelegramApiException $e) {
            if (! $e->isBlockedByUser()) {
                throw $e;
            }
            $contact->forceFill(['blocked_at' => now()])->save();
            $this->topics->note($contact, '⛔ Не доставлено: человек заблокировал бота.');
            $this->topics->refreshStatus($contact);

            return null;
        }

        TelegramMessage::create([
            'telegram_contact_id' => $contact->id,
            'direction' => TelegramMessage::DIRECTION_OUT,
            'kind' => TelegramMessage::KIND_AUTO,
            'step' => $step,
            'text' => html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5),
            'telegram_message_id' => $sent['message_id'] ?? null,
        ]);

        $this->topics->note($contact, "🤖 <i>Бот:</i>\n".$html);

        return $sent;
    }

    /** Оставить под приветствием только кнопку урока — чтобы не жали оценку второй раз. */
    private function removeRatingButtons(TelegramContact $contact): void
    {
        if (! $contact->rating_message_id) {
            return;
        }

        try {
            $this->api->call('editMessageReplyMarkup', [
                'chat_id' => $contact->telegram_user_id,
                'message_id' => $contact->rating_message_id,
                'reply_markup' => ['inline_keyboard' => [[$this->lessonButton($contact, $contact->lesson)]]],
            ]);
        } catch (TelegramApiException $e) {
            // Сообщение старше 48 ч или уже без кнопок — не критично.
            Log::info('Telegram funnel: не удалось убрать кнопки оценки', ['contact' => $contact->id, 'error' => $e->getMessage()]);
        }
    }

    private function answerCallback(string $callbackId, ?string $text = null): void
    {
        try {
            $this->api->call('answerCallbackQuery', array_filter(['callback_query_id' => $callbackId, 'text' => $text]));
        } catch (TelegramApiException) {
            // кнопка просто перестанет «крутиться» сама через пару секунд
        }
    }

    private function lessonButton(TelegramContact $contact, string $code): array
    {
        return ['text' => '▶️ Смотреть урок «'.FunnelLessons::title($code).'»', 'url' => FunnelLessons::trackedUrl($contact, $code)];
    }

    private function vars(TelegramContact $contact): array
    {
        return [
            'lesson' => FunnelLessons::title($contact->lesson),
            'bonus' => FunnelLessons::title(FunnelLessons::bonusFor($contact->lesson)),
            'name' => $contact->first_name ?: '',
        ];
    }

    private function dispatch(TelegramContact $contact, string $step, array $payload = [], int $delaySeconds = 0): void
    {
        $job = RunFunnelStep::dispatch($contact->id, $step, $payload)->onQueue(config('telegram_funnel.queue'));
        if ($delaySeconds > 0) {
            $job->delay(now()->addSeconds($delaySeconds));
        }
    }
}
