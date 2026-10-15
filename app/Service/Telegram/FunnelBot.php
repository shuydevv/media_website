<?php

namespace App\Service\Telegram;

use App\Jobs\Telegram\RunFunnelStep;
use App\Models\SiteLead;
use App\Models\TelegramContact;
use App\Models\TelegramMessage;
use Carbon\Carbon;
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

        // Правку сообщения разбираем только для /emoji в админ-группе:
        // «отправил /emoji, потом дописал эмодзи» — частый случай. Остальные
        // правки (человека или своего ответа) пока не обрабатываем.
        $edited = $update['edited_message'] ?? null;
        if ($edited && isset($edited['chat']['id']) && AdminTopics::isAdminChat($edited['chat']['id'])
            && preg_match('~^/emoji(?:@\w+)?(?![A-Za-z0-9_])~', $edited['text'] ?? '')) {
            $this->replyCustomEmojiIds($edited);

            return;
        }

        $message = $update['message'] ?? null;
        if (! $message || ! isset($message['chat']['id'])) {
            return;
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
            'like_human' => $this->sendLikeHuman($contact, (int) ($payload['since'] ?? 0)),
            'lead_ack' => $this->sendHuman($contact, 'lead_ack'),
            'lead_topic' => ($lead = SiteLead::find($payload['lead_id'] ?? 0)) ? $this->topics->openLeadTopic($contact, $lead) : null,
            'reminder' => $this->sendReminder($contact),
            'remove_rating_buttons' => $this->removeRatingButtons($contact, ($payload['bonus'] ?? false) === true),
            'topic' => $this->topics->ensureTopic($contact),
            'start_feed' => $this->topics->postStart($contact),
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
        if ($param !== null && preg_match('/^lead_([A-Za-z0-9]{10,24})$/', $param, $m)
            && ($lead = SiteLead::where('token', $m[1])->first())) {
            $this->handleLeadStart($from, $lead);

            return;
        }

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

    /**
     * Запуск по кнопке «Получить ответ в Telegram» со страницы «Спасибо»
     * (после формы «Записаться»). Урок такому человеку не нужен — он уже
     * хочет на курс: короткий ответ от Александра и сразу тема в группе с
     * заявкой, чтобы ответить через бота. Тема заводится сразу, а не по
     * первому сообщению: человек сам сделал шаг, и это заявка — о ней
     * уведомление и нужно.
     */
    private function handleLeadStart(array $from, SiteLead $lead): void
    {
        $contact = TelegramContact::where('telegram_user_id', $from['id'])->first();
        if (! $contact) {
            $contact = $this->createContact($from, config('telegram_funnel.default_lesson'), 'lead', false);
        } else {
            $contact->forceFill([
                'username' => $from['username'] ?? null,
                'first_name' => $from['first_name'] ?? null,
                'last_name' => $from['last_name'] ?? null,
                'last_start_at' => now(),
                'blocked_at' => null,
                // Дальше ведёт живой человек — суточное напоминание про урок невпопад.
                'reminder_due_at' => null,
            ])->save();
        }

        // Повторный запуск по той же ссылке — второй раз не здороваемся.
        if ($lead->telegram_contact_id === $contact->id) {
            $this->dispatch($contact, 'note', ['html' => '🔁 Повторный запуск по ссылке из заявки']);

            return;
        }

        $lead->forceFill(['telegram_contact_id' => $contact->id])->save();
        $this->dispatch($contact, 'lead_topic', ['lead_id' => $lead->id]);
        $this->dispatch($contact, 'lead_ack');
    }

    private function createContact(array $from, string $lesson, ?string $source, bool $withReminder = true): TelegramContact
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
            'reminder_due_at' => $withReminder ? ReminderSchedule::randomFor($now) : null,
        ]);

        // Строка в «📥 Старты» — без звука; тема человека появится, только
        // когда он напишет сам (см. AdminTopics).
        $this->dispatch($contact, 'start_feed');

        return $contact;
    }

    /**
     * 👍/👎 под основным уроком (rate:up) или под бонусным (rate:up:bonus) —
     * оценки независимые, у каждой своя кнопка и своё «уже оценил». Любое
     * нажатие отменяет суточное напоминание.
     */
    private function handleCallback(array $callback): void
    {
        $data = $callback['data'] ?? '';
        $contact = TelegramContact::where('telegram_user_id', $callback['from']['id'] ?? 0)->first();

        if (! $contact || ! preg_match('/^rate:(up|down)(:bonus)?$/', $data, $m)) {
            $this->answerCallback($callback['id']);

            return;
        }

        $bonus = ($m[2] ?? '') !== '';
        [$ratingField, $ratedAtField, $messageField] = $bonus
            ? ['bonus_rating', 'bonus_rated_at', 'bonus_rating_message_id']
            : ['rating', 'rated_at', 'rating_message_id'];

        if ($contact->{$ratingField} !== null) {
            $this->answerCallback($callback['id'], 'Оценка уже есть — спасибо!');

            return;
        }

        // Вопрос «что понравилось?» уже ушёл (или в пути), если раньше лайкнули другой урок.
        $likedBefore = $contact->rating === TelegramContact::RATING_UP || $contact->bonus_rating === TelegramContact::RATING_UP;

        $rating = $m[1] === 'up' ? TelegramContact::RATING_UP : TelegramContact::RATING_DOWN;
        $contact->forceFill([
            $ratingField => $rating,
            $ratedAtField => now(),
            $messageField => $callback['message']['message_id'] ?? $contact->{$messageField},
            'reminder_due_at' => null,
        ])->save();

        $this->answerCallback($callback['id']);

        $what = $bonus
            ? 'бонусный урок «'.e(FunnelLessons::title(FunnelLessons::bonusFor($contact->lesson))).'»'
            : 'урок «'.e(FunnelLessons::title($contact->lesson)).'»';
        $this->dispatch($contact, 'remove_rating_buttons', ['bonus' => $bonus]);
        $this->dispatch($contact, 'note', [
            'html' => ($rating === TelegramContact::RATING_UP ? '👍 Понравился ' : '👎 Не понравился ').$what,
        ]);
        $this->dispatch($contact, 'refresh_status');

        if ($rating === TelegramContact::RATING_UP) {
            $this->dispatch($contact, 'like_auto');
            if (! $likedBefore) {
                $this->dispatch($contact, 'like_human', ['since' => now()->timestamp], config('telegram_funnel.timing.like_human_delay_seconds'));
            }
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

        // /emoji — до пересылки: в теме человека это команда боту, а не ответ.
        if (! ($from['is_bot'] ?? false) && preg_match('~^/emoji(?:@\w+)?(?![A-Za-z0-9_])~', $message['text'] ?? '')) {
            $this->replyCustomEmojiIds($message);

            return;
        }

        // Только обычные сообщения людей внутри темы: служебные (тема
        // создана/переименована), «General» и свои сообщения бота — мимо.
        if (! $threadId || ! ($message['is_topic_message'] ?? false) || ($from['is_bot'] ?? false)
            || isset($message['forum_topic_created']) || isset($message['forum_topic_edited'])) {
            return;
        }

        $body = ltrim($message['text'] ?? $message['caption'] ?? '');
        if (str_starts_with($body, '//')) {
            return; // заметка для себя
        }
        // /команда — для бота (или опечатка в ней), человеку её слать нельзя.
        if (preg_match('~^/[A-Za-z]~', $body)) {
            return;
        }

        $contact = TelegramContact::where('topic_id', $threadId)->first();
        if (! $contact) {
            return;
        }

        $this->dispatch($contact, 'admin_reply', ['message' => $message]);
    }

    /**
     * /emoji с премиум-эмодзи в том же сообщении → бот отвечает записями
     * {e:<id>:<эмодзи>} для текстов в админке (id иначе не узнать: в браузер
     * копируется только обычный эмодзи-заменитель) и тут же шлёт эти эмодзи
     * своим сообщением. Анимированные — боту можно их слать; стали обычными —
     * Telegram ему пока не разрешает (нужен username с Fragment), и в текстах
     * человек увидит заменитель.
     */
    private function replyCustomEmojiIds(array $message): void
    {
        $text = $message['text'];
        $utf16 = mb_convert_encoding($text, 'UTF-16LE', 'UTF-8');

        $found = [];
        foreach ($message['entities'] ?? [] as $entity) {
            if (($entity['type'] ?? '') !== 'custom_emoji') {
                continue;
            }
            $fallback = mb_convert_encoding(substr($utf16, $entity['offset'] * 2, $entity['length'] * 2), 'UTF-8', 'UTF-16LE');
            $found[$entity['custom_emoji_id']] = $fallback;
        }

        $reply = ['message_thread_id' => $message['message_thread_id'] ?? null];
        if ($found === []) {
            $this->api->sendMessage($message['chat']['id'], 'Отправьте <code>/emoji</code> и в том же сообщении премиум-эмодзи — пришлю их коды для текстов бота.', array_filter($reply));

            return;
        }

        $lines = ['Коды для текстов в админке (нажмите, чтобы скопировать):', ''];
        foreach ($found as $id => $fallback) {
            $lines[] = e($fallback).' <code>{e:'.$id.':'.e($fallback).'}</code>';
        }
        $lines[] = '';
        $lines[] = 'Проверка — так их отправляет бот: '.implode(' ', array_map(
            fn ($id, $fallback) => '<tg-emoji emoji-id="'.$id.'">'.e($fallback).'</tg-emoji>',
            array_keys($found), $found,
        ));
        $lines[] = '<i>Анимированные — всё работает. Обычные — Telegram пока не даёт боту премиум-эмодзи, люди увидят обычный смайл.</i>';

        $this->api->sendMessage($message['chat']['id'], implode("\n", $lines), array_filter($reply));
    }

    private function sendWelcome(TelegramContact $contact): void
    {
        $sent = $this->sendLessonMessage($contact, 'welcome', $contact->lesson, $this->ratingRow($contact->rating, ''));

        if ($sent && $contact->rating === null) {
            $contact->forceFill(['rating_message_id' => $sent['message_id']])->save();
        }
    }

    private function sendBonus(TelegramContact $contact): void
    {
        $sent = $this->sendLessonMessage($contact, $this->bonusKey($contact), FunnelLessons::bonusFor($contact->lesson),
            $this->ratingRow($contact->bonus_rating, ':bonus'));

        if ($sent && $contact->bonus_rating === null) {
            $contact->forceFill(['bonus_rating_message_id' => $sent['message_id']])->save();
        }
    }

    /** Кнопки 👍 👎, пока этот урок не оценён. */
    private function ratingRow(?string $rating, string $suffix): array
    {
        return $rating === null ? [[
            ['text' => '👍', 'callback_data' => 'rate:up'.$suffix],
            ['text' => '👎', 'callback_data' => 'rate:down'.$suffix],
        ]] : [];
    }

    /**
     * Текст бонуса зависит от того, «сложнее» он основного или «проще»:
     * первый урок в config/telegram_funnel.lessons — лёгкий.
     */
    private function bonusKey(TelegramContact $contact): string
    {
        return $contact->lesson === FunnelLessons::codes()[0] ? 'bonus_harder' : 'bonus_easier';
    }

    /**
     * Сообщение с уроком: [СМОТРЕТЬ] в тексте — персональная ссылка, под
     * текстом — крупное превью страницы урока с обложкой (og:image, см.
     * main/trial-lesson). Превью строится по той же персональной ссылке:
     * тап по карточке тоже засчитывается как «открыл урок», а заход самого
     * Telegram за превью LessonLinkController отсекает по User-Agent. Если
     * в тексте ссылки нет — урок отдельной кнопкой, как раньше.
     */
    private function sendLessonMessage(TelegramContact $contact, string $key, string $lesson, array $rows = []): ?array
    {
        $url = FunnelLessons::trackedUrl($contact, $lesson);
        if (! FunnelTexts::hasLink($key)) {
            array_unshift($rows, [$this->lessonButton($contact, $lesson)]);
        }

        $extra = [
            'link_preview_options' => ['url' => $url, 'prefer_large_media' => true],
        ];
        if ($rows) {
            $extra['reply_markup'] = ['inline_keyboard' => $rows];
        }

        return $this->sendAuto($contact, $key, FunnelTexts::render($key, $this->vars($contact), $url), $extra);
    }

    private function sendLikeHuman(TelegramContact $contact, int $since): void
    {
        // Если за эти ~20 секунд человек уже сам что-то написал или ему
        // ответил админ — вопрос «что понравилось?» был бы невпопад. Один
        // раз на человека, даже если лайкнул оба урока.
        $sinceAt = $since > 0 ? Carbon::createFromTimestamp($since) : ($contact->rated_at ?? $contact->bonus_rated_at);
        $repliedSince = fn ($at) => $at !== null && $sinceAt !== null && $at->gte($sinceAt);
        $liked = $contact->rating === TelegramContact::RATING_UP || $contact->bonus_rating === TelegramContact::RATING_UP;
        if ($contact->blocked_at || ! $liked
            || $repliedSince($contact->last_user_message_at) || $repliedSince($contact->last_admin_message_at)
            || $contact->messages()->where('step', 'like_human')->exists()) {
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

        // В копии для темы — без персональной ссылки: клик админа по ней
        // засчитался бы человеку как «открыл урок».
        $this->topics->note($contact, "🤖 <i>Бот:</i>\n".preg_replace('~<a href="[^"]*">(.*?)</a>~su', '$1', $html));

        return $sent;
    }

    /** Убрать кнопки оценки из-под оценённого урока (кнопку урока, если она была, — оставить). */
    private function removeRatingButtons(TelegramContact $contact, bool $bonus): void
    {
        $messageId = $bonus ? $contact->bonus_rating_message_id : $contact->rating_message_id;
        if (! $messageId) {
            return;
        }

        $key = $bonus ? $this->bonusKey($contact) : 'welcome';
        $lesson = $bonus ? FunnelLessons::bonusFor($contact->lesson) : $contact->lesson;

        try {
            $this->api->call('editMessageReplyMarkup', [
                'chat_id' => $contact->telegram_user_id,
                'message_id' => $messageId,
                'reply_markup' => ['inline_keyboard' => FunnelTexts::hasLink($key) ? [] : [[$this->lessonButton($contact, $lesson)]]],
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
