<?php

namespace App\Service\Telegram;

use App\Jobs\Telegram\RunFunnelStep;
use App\Models\SiteLead;
use App\Models\TelegramContact;
use App\Models\TelegramMessage;
use App\Service\Telegram\Broadcast\BroadcastDesk;
use App\Service\Telegram\Broadcast\BroadcastSender;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Сценарий бота lesson (TelegramBots). Он же «главный» бот в общей
 * админ-группе: отвечает на /chatid и /emoji, ведёт служебные темы, в том
 * числе «📣 Рассылка» (Broadcast\BroadcastDesk).
 *
 * Воронка пробного урока (исходный путь — «TG инструкция.md»):
 *
 *   /start <урок>_<метка> → приветствие с уроком, через ~5 с бонусный
 *     (второй) урок. Кнопок оценки под ними нет: оценивают под видео на
 *     сайте (Telegram\TrialFeedbackController) или в «Как вам урок?».
 *
 *   Просмотр — плеером Kinescope на странице урока (trackProgress):
 *     досмотрел до 80% → через 5 минут «Как вам урок?» с кнопками
 *       (если ещё не оценил на сайте);
 *     открыл и ушёл, ни один урок не досмотрев → через 6 часов одно
 *       напоминание по уроку, который смотрел больше: меньше 20% —
 *       «Получилось посмотреть?» от Александра, 20–80% — «Как вам урок?»
 *       с кнопками и ссылкой «продолжить с того же места»;
 *     не открыл ни один → «Получилось посмотреть?» через 22–26 ч.
 *
 *   Молчит после «Как вам урок?» или вопроса после оценки, хотя досмотрел
 *     или оценил хорошо → через сутки одно второе касание от Александра
 *     (sendFollowup).
 *
 *   Оценка (applyRating): «Отлично»/«Хорошо» → через 1–4 минуты вопрос от
 *     Александра, один на человека (текст — по последней хорошей оценке в
 *     момент отправки); «Плохо» в боте → автоответ, на сайте — ничего.
 *     Любая оценка останавливает напоминания.
 *
 * «Человеческие» сообщения от Александра — только в 10:00–21:00 (ночью
 * переносятся на утро, кроме ответа на заявку), с «печатает…» по длине
 * текста. Как только человек написал сам или ему ответил админ —
 * автосообщения не уходят, дальше ведёт живой человек (AdminTopics).
 *
 * Отложенные сообщения — полями *_due_at (их раз в минуту разбирает
 * telegram:send-reminders), а условия проверяются в момент отправки — это
 * и есть «отмена таймера». handleUpdate() пишет в БД и раскладывает
 * отправки по задачам RunFunnelStep — по одной на задачу, чтобы повтор
 * упавшей не слал человеку дубль.
 */
class FunnelBot implements BotScenario
{
    /** Контакты этого сценария — telegram_contacts.bot (TelegramBots). */
    public const BOT = TelegramBots::LESSON;

    /** Поле с моментом отправки → шаг; разбирает SendTelegramReminders. */
    public const DUE_STEPS = [
        'reminder_due_at' => ['reminder', []],
        'nudge_due_at' => ['nudge', []],
        'question_due_at' => ['question', []],
        'lesson_ask_due_at' => ['rate_ask', ['bonus' => false]],
        'bonus_ask_due_at' => ['rate_ask', ['bonus' => true]],
        'followup_due_at' => ['followup', []],
    ];

    public function __construct(
        private readonly TelegramApi $api,
        private readonly AdminTopics $topics,
    ) {
    }

    public static function make(): self
    {
        $api = TelegramApi::forBot(self::BOT);

        return new self($api, new AdminTopics($api));
    }

    public function dueSteps(): array
    {
        return self::DUE_STEPS;
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

        // Правку сообщения разбираем только в админ-группе: для /emoji
        // («отправил /emoji, потом дописал эмодзи» — частый случай) и в теме
        // рассылок (поправили черновик). Остальные правки (человека или
        // своего ответа) пока не обрабатываем.
        $edited = $update['edited_message'] ?? null;
        if ($edited && isset($edited['chat']['id']) && AdminTopics::isAdminChat($edited['chat']['id'])) {
            if (preg_match('~^/emoji(?:@\w+)?(?![A-Za-z0-9_])~', $edited['text'] ?? '')) {
                $this->replyCustomEmojiIds($edited);
            } else {
                BroadcastDesk::make()->handleEdited($edited);
            }

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
            'welcome' => $this->sendLessonMessage($contact, 'welcome', $contact->lesson),
            'bonus' => $this->sendLessonMessage($contact, $this->bonusKey($contact), FunnelLessons::bonusFor($contact->lesson)),
            'rate_ask' => $this->sendRateAsk($contact, ($payload['bonus'] ?? false) === true, false),
            'question' => $this->sendQuestion($contact),
            'bad_auto' => $this->sendAuto($contact, 'bad_auto', FunnelTexts::render('bad_auto', $this->vars($contact))),
            'nudge' => $this->sendNudge($contact),
            'followup' => $this->sendFollowup($contact),
            'reminder' => $this->sendReminder($contact),
            // lead_ack — старое имя шага (задачи, поставленные до замены текста).
            'site_lead_question', 'lead_ack' => $contact->blocked_at ? null : $this->sendHuman($contact, 'site_lead_question'),
            'lead_topic' => ($lead = SiteLead::find($payload['lead_id'] ?? 0))
                ? $this->topics->openLeadTopic($contact, $lead, ($payload['from_site'] ?? false) === true) : null,
            'topic' => $this->topics->ensureTopic($contact),
            'start_feed' => $this->topics->postStart($contact),
            'liked_feed' => $this->topics->postLiked($contact, ($payload['bonus'] ?? false) === true),
            'forward' => $this->topics->forward($contact, (int) $payload['message_id']),
            'note' => $this->topics->note($contact, $payload['html'], $payload['silent'] ?? true),
            'admin_reply' => $this->topics->relayAdminMessage($contact, $payload['message']),
            'refresh_status' => $this->topics->refreshStatus($contact),
            default => Log::warning("Telegram funnel: неизвестный шаг {$step}"),
        };
    }

    /**
     * Оценка урока — из бота (кнопки «Как вам урок?») или с сайта (под
     * видео). На сайте можно переголосовать: считается последняя оценка.
     */
    public function applyRating(TelegramContact $contact, bool $bonus, string $rating, string $source): void
    {
        $f = TelegramContact::slotFields($bonus);
        $previous = $contact->{$f['rating']};
        if ($previous === $rating) {
            return;
        }

        $contact->forceFill([
            $f['rating'] => $rating,
            $f['rated_at'] => now(),
            $f['rating_source'] => $source,
            // Оценил — напоминания «получилось посмотреть?» больше не нужны,
            // а «Как вам урок?» по этому уроку — тем более.
            $f['ask_due_at'] => null,
            'reminder_due_at' => null,
            'nudge_due_at' => null,
        ]);

        // Вопрос от Александра — один на человека. Если уже запланирован,
        // не сдвигаем: какой из двух текстов уйдёт, решит последняя хорошая
        // оценка в момент отправки (sendQuestion).
        if (TelegramContact::isGoodRating($rating) && $contact->question_sent_at === null && $contact->question_due_at === null) {
            $t = config('telegram_funnel.timing');
            $contact->question_due_at = ReminderSchedule::inWindow(
                now()->addSeconds(random_int($t['question_delay_min_seconds'], $t['question_delay_max_seconds']))
            );
        }
        $contact->save();

        $code = $bonus ? FunnelLessons::bonusFor($contact->lesson) : $contact->lesson;
        $this->dispatch($contact, 'note', [
            'html' => e(FunnelTexts::ratingLabel($rating)).' — урок «'.e(FunnelLessons::title($code)).'»'.($source === 'site' ? ' · на сайте' : ''),
        ]);
        $this->dispatch($contact, 'refresh_status');

        // В «🔥 Понравилось» — когда урок впервые оценён хорошо (переголосование
        // «Отлично» → «Хорошо» второй строкой не дублируем).
        if (TelegramContact::isGoodRating($rating) && ! TelegramContact::isGoodRating($previous)) {
            $this->dispatch($contact, 'liked_feed', ['bonus' => $bonus]);
        }

        // «Плохо» в боте — человек уже в переписке, вопрос естественен; на
        // сайте — ничего: там он не должен знать, что ему напишут в Telegram.
        if ($rating === TelegramContact::RATING_BAD && $source === 'bot') {
            $this->dispatch($contact, 'bad_auto');
        }
    }

    /**
     * «Записаться» на странице урока от пришедшего из бота: тема сразу (это
     * заявка — уведомление нужно), а через 1–3 минуты Александр пишет
     * первым. Остальные автосообщения больше не нужны: дальше живой
     * диалог; вопрос после оценки, если ещё не ушёл, заменяет этот — чтобы
     * не пришло два вопроса от Александра подряд.
     */
    public function leadFromSite(TelegramContact $contact, SiteLead $lead): void
    {
        $contact->forceFill([
            'reminder_due_at' => null,
            'nudge_due_at' => null,
            'lesson_ask_due_at' => null,
            'bonus_ask_due_at' => null,
            'question_due_at' => null,
            'question_sent_at' => $contact->question_sent_at ?? now(),
            'followup_due_at' => null,
        ])->save();

        $this->dispatch($contact, 'lead_topic', ['lead_id' => $lead->id, 'from_site' => true]);

        $t = config('telegram_funnel.timing');
        $this->dispatch($contact, 'site_lead_question', [], random_int($t['lead_ack_delay_min_seconds'], $t['lead_ack_delay_max_seconds']));
    }

    /** Переход по персональной ссылке на урок (Telegram\LessonLinkController). */
    public function trackOpen(TelegramContact $contact, bool $bonus): void
    {
        $f = TelegramContact::slotFields($bonus);
        if ($contact->{$f['opened_at']} !== null) {
            return;
        }

        $contact->{$f['opened_at']} = now();
        $this->scheduleNudge($contact);
        $contact->save();

        $code = $bonus ? FunnelLessons::bonusFor($contact->lesson) : $contact->lesson;
        $this->dispatch($contact, 'note', ['html' => '👀 Открыл '.($bonus ? 'бонусный ' : '').'урок «'.e(FunnelLessons::title($code)).'»']);
        $this->dispatch($contact, 'refresh_status');
    }

    /**
     * Отметка плеера со страницы урока (раз в ~30 с, пока видео играет).
     * $seconds — реально просмотренное с прошлой отметки, без перемотки.
     */
    public function trackProgress(TelegramContact $contact, bool $bonus, int $seconds, int $position, int $duration): void
    {
        $f = TelegramContact::slotFields($bonus);
        $t = config('telegram_funnel.timing');

        $watched = (int) $contact->{$f['watch_seconds']} + min(max($seconds, 0), 120);
        $duration = max($duration, (int) $contact->{$f['duration']});
        $progress = $duration > 0 ? (int) min(100, floor($watched / $duration * 100)) : 0;

        $contact->forceFill([
            $f['watch_seconds'] => $watched,
            $f['duration'] => $duration ?: null,
            $f['position'] => max(0, $position),
            $f['progress'] => max($progress, (int) $contact->{$f['progress']}),
            $f['last_watch_at'] => now(),
            $f['opened_at'] => $contact->{$f['opened_at']} ?? now(),
        ]);

        $justWatched = $progress >= $t['watched_percent'] && $contact->{$f['watched_at']} === null;
        if ($justWatched) {
            $contact->{$f['watched_at']} = now();
            if ($contact->{$f['rating']} === null && $contact->{$f['ask_sent_at']} === null) {
                $contact->{$f['ask_due_at']} = now()->addSeconds($t['ask_after_watch_seconds']);
            }
        }

        // «Ушёл, не досмотрев» отсчитываем от последнего просмотра.
        $this->scheduleNudge($contact);
        $contact->save();

        if ($justWatched) {
            $code = $bonus ? FunnelLessons::bonusFor($contact->lesson) : $contact->lesson;
            $this->dispatch($contact, 'note', ['html' => '▶️ Досмотрел '.($bonus ? 'бонусный ' : '').'урок «'.e(FunnelLessons::title($code)).'»']);
            $this->dispatch($contact, 'refresh_status');
        }
    }

    /** Отметка плеера со страницы урока: ready | play | fallback (для карточки в админке). */
    public function trackPlayerEvent(TelegramContact $contact, bool $bonus, string $event): void
    {
        $p = $bonus ? 'bonus' : 'lesson';
        if ($event === 'play') {
            $contact->{"{$p}_played_at"} ??= now();
        } else {
            $contact->forceFill(["{$p}_player_status" => $event, "{$p}_player_at" => now()]);
        }
        $contact->save();
    }

    private function scheduleNudge(TelegramContact $contact): void
    {
        if ($contact->nudge_sent_at === null && ! $contact->watchedAny() && ! $contact->ratedAny()) {
            $contact->nudge_due_at = ReminderSchedule::inWindow(now()->addMinutes(config('telegram_funnel.timing.nudge_after_minutes')));
        } elseif ($contact->watchedAny()) {
            $contact->nudge_due_at = null;
        }
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
        $contact = TelegramContact::forBot(self::BOT)->where('telegram_user_id', $from['id'])->first()
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
        $this->noteBroadcastReply($contact);
        $this->dispatch($contact, 'refresh_status');
    }

    /** Первое сообщение после рассылки — строкой в теме, на что это ответ (после пересылки: тема уже есть). */
    private function noteBroadcastReply(TelegramContact $contact): void
    {
        if ($recipient = BroadcastSender::recordReply($contact)) {
            $this->dispatch($contact, 'note', ['html' => '📣 Ответ на рассылку #'.$recipient->telegram_broadcast_id
                .' от '.$recipient->sent_at->format('d.m H:i').': '.e($recipient->broadcast->preview(120))]);
        }
    }

    private function handleStart(array $from, ?string $param): void
    {
        if ($param !== null && preg_match('/^lead_([A-Za-z0-9]{10,24})$/', $param, $m)
            && ($lead = SiteLead::where('token', $m[1])->first())) {
            $this->handleLeadStart($from, $lead);

            return;
        }

        $parsed = StartParam::parse($param, FunnelLessons::codes(), config('telegram_funnel.default_lesson'));
        $contact = TelegramContact::forBot(self::BOT)->where('telegram_user_id', $from['id'])->first();

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
     * хочет на курс: вопрос от Александра и сразу тема в группе с
     * заявкой, чтобы ответить через бота. Тема заводится сразу, а не по
     * первому сообщению: человек сам сделал шаг, и это заявка — о ней
     * уведомление и нужно.
     */
    private function handleLeadStart(array $from, SiteLead $lead): void
    {
        $contact = TelegramContact::forBot(self::BOT)->where('telegram_user_id', $from['id'])->first();
        if (! $contact) {
            $contact = $this->createContact($from, config('telegram_funnel.default_lesson'), 'lead', false);
        } else {
            $contact->forceFill([
                'username' => $from['username'] ?? null,
                'first_name' => $from['first_name'] ?? null,
                'last_name' => $from['last_name'] ?? null,
                'last_start_at' => now(),
                'blocked_at' => null,
                // Дальше ведёт живой человек — напоминания про урок невпопад,
                // а вопрос после оценки заменяет вопрос про класс.
                'reminder_due_at' => null,
                'nudge_due_at' => null,
                'question_due_at' => null,
                'question_sent_at' => $contact->question_sent_at ?? now(),
            ])->save();
        }

        // Повторный запуск по той же ссылке — второй раз не здороваемся.
        if ($lead->telegram_contact_id === $contact->id) {
            $this->dispatch($contact, 'note', ['html' => '🔁 Повторный запуск по ссылке из заявки']);

            return;
        }

        $lead->forceFill(['telegram_contact_id' => $contact->id])->save();
        $this->dispatch($contact, 'lead_topic', ['lead_id' => $lead->id]);

        // Человек сам нажал «Получить ответ» и ждёт — поэтому быстрее, чем
        // вопрос после оценки, и без ночной паузы.
        $t = config('telegram_funnel.timing');
        // Не «заявку получил», а сразу живой вопрос — как и тем, кто записался
        // со страницы урока (leadFromSite).
        $this->dispatch($contact, 'site_lead_question', [], random_int($t['lead_ack_delay_min_seconds'], $t['lead_ack_delay_max_seconds']));
    }

    private function createContact(array $from, string $lesson, ?string $source, bool $withReminder = true): TelegramContact
    {
        $now = now();
        $contact = TelegramContact::create([
            'bot' => self::BOT,
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
     * Кнопка оценки под «Как вам урок?» (rate:great, rate:good:bonus …).
     * Разбирается сразу в webhook-запросе, а не в очереди (WebhookController):
     * подтверждение должно появиться мгновенно — подсказка «Спасибо за
     * оценку!» и строка «✅ Ваша оценка» вместо кнопок.
     */
    private function handleCallback(array $callback): void
    {
        $data = $callback['data'] ?? '';
        if (str_starts_with($data, 'bc:')) {
            BroadcastDesk::make()->handleCallback($callback);

            return;
        }
        $contact = TelegramContact::forBot(self::BOT)->where('telegram_user_id', $callback['from']['id'] ?? 0)->first();

        // up/down — кнопки старых сообщений, до трёх уровней оценки.
        if (! $contact || ! preg_match('/^rate:(great|good|bad|up|down)(:bonus)?$/', $data, $m)) {
            $this->answerCallback($callback['id']);

            return;
        }

        $rating = ['up' => TelegramContact::RATING_GREAT, 'down' => TelegramContact::RATING_BAD][$m[1]] ?? $m[1];
        $bonus = ($m[2] ?? '') !== '';

        if ($contact->slot($bonus, 'rating') !== null) {
            $this->answerCallback($callback['id'], 'Оценка уже есть — спасибо!');
            $this->markRated($callback['message'] ?? null, $contact->slot($bonus, 'rating'));

            return;
        }

        $this->answerCallback($callback['id'], 'Спасибо за оценку!');
        $this->markRated($callback['message'] ?? null, $rating);
        $this->applyRating($contact, $bonus, $rating, 'bot');
    }

    /** Кнопки оценки → строка «✅ Ваша оценка: …» в конце того же сообщения. */
    private function markRated(?array $message, string $rating): void
    {
        if (! $message || ! isset($message['message_id'], $message['chat']['id'])) {
            return;
        }

        $text = ($message['text'] ?? '')."\n\n✅ Ваша оценка: ".FunnelTexts::ratingLabel($rating);
        try {
            $this->api->call('editMessageText', [
                'chat_id' => $message['chat']['id'],
                'message_id' => $message['message_id'],
                'text' => $text,
                // Те же entities: текст сообщения не меняется, только дописывается в конец.
                'entities' => $message['entities'] ?? [],
                'link_preview_options' => ['is_disabled' => true],
                'reply_markup' => ['inline_keyboard' => []],
            ]);
        } catch (TelegramApiException $e) {
            // Уже отмечено (повторное нажатие) или сообщение старше 48 ч — не критично.
            Log::info('Telegram funnel: не удалось отметить оценку в сообщении', ['error' => $e->getMessage()]);
        }
    }

    private function handleChatMember(array $update): void
    {
        if (($update['chat']['type'] ?? null) !== 'private') {
            return;
        }
        $contact = TelegramContact::forBot(self::BOT)->where('telegram_user_id', $update['from']['id'] ?? 0)->first();
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

        // Тема «📣 Рассылка», /broadcast, /norassylka и /rassylka в темах людей.
        if (BroadcastDesk::make()->handleAdminMessage($message)) {
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

        // Группа общая для всех ботов: тема другого бота — не наша, ответ
        // туда отправит тот бот, которого этот человек запускал.
        $contact = TelegramContact::forBot(self::BOT)->where('topic_id', $threadId)->first();
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
        $check = [
            '',
            'Проверка — так их отправляет бот: '.implode(' ', array_map(
                fn ($id, $fallback) => '<tg-emoji emoji-id="'.$id.'">'.e($fallback).'</tg-emoji>',
                array_keys($found), $found,
            )),
            '<i>Анимированные — всё работает. Обычные — Telegram пока не даёт боту премиум-эмодзи, люди увидят обычный смайл.</i>',
        ];

        try {
            $this->api->sendMessage($message['chat']['id'], implode("\n", array_merge($lines, $check)), array_filter($reply));
        } catch (TelegramApiException $e) {
            // Telegram отклонил проверочную строку с премиум-эмодзи — коды всё
            // равно отдаём, а причину пишем в чат: без этого команда просто
            // молчала, и было не понять, что не так.
            $lines[] = '';
            $lines[] = '⚠️ Эти эмодзи Telegram от бота не принял — в текстах бот заменит их обычными. Ответ Telegram: <code>'.e($e->getMessage()).'</code>';
            $this->api->sendMessage($message['chat']['id'], implode("\n", $lines), array_filter($reply));
        }
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
     * в тексте ссылки нет — урок отдельной кнопкой.
     */
    private function sendLessonMessage(TelegramContact $contact, string $key, string $lesson): ?array
    {
        $url = FunnelLessons::trackedUrl($contact, $lesson);
        $extra = ['link_preview_options' => ['url' => $url, 'prefer_large_media' => true]];
        if (! FunnelTexts::hasLink($key)) {
            $extra['reply_markup'] = ['inline_keyboard' => [[$this->lessonButton($contact, $lesson)]]];
        }

        return $this->sendAuto($contact, $key, FunnelTexts::render($key, $this->vars($contact), $url), $extra);
    }

    /**
     * «Как вам урок?» с кнопками оценки. $partial — человек ушёл на
     * середине: в тексте ссылка «продолжить с того же места».
     */
    private function sendRateAsk(TelegramContact $contact, bool $bonus, bool $partial): void
    {
        $f = TelegramContact::slotFields($bonus);
        if ($contact->blocked_at || $contact->{$f['rating']} !== null || $contact->{$f['ask_sent_at']} !== null
            || $contact->first_user_message_at !== null || $contact->first_admin_message_at !== null) {
            return;
        }

        $code = $bonus ? FunnelLessons::bonusFor($contact->lesson) : $contact->lesson;
        $key = $partial ? 'rate_ask_partial' : 'rate_ask';
        // На пару секунд раньше места, где остановился, — чтобы не с полуслова.
        $url = FunnelLessons::trackedUrl($contact, $code, $partial ? max(0, (int) $contact->{$f['position']} - 5) : 0);

        $suffix = $bonus ? ':bonus' : '';
        $rows = [[
            ['text' => FunnelTexts::plain('btn_great'), 'callback_data' => 'rate:great'.$suffix],
            ['text' => FunnelTexts::plain('btn_good'), 'callback_data' => 'rate:good'.$suffix],
            ['text' => FunnelTexts::plain('btn_bad'), 'callback_data' => 'rate:bad'.$suffix],
        ]];
        if ($partial && ! FunnelTexts::hasLink($key)) {
            array_unshift($rows, [['text' => '▶️ Продолжить урок', 'url' => $url]]);
        }

        $sent = $this->sendAuto($contact, $key, FunnelTexts::render($key, $this->vars($contact, $code), $url), [
            'reply_markup' => ['inline_keyboard' => $rows],
        ]);

        $contact->forceFill([
            $f['ask_sent_at'] => now(),
            $f['message_id'] => $sent['message_id'] ?? $contact->{$f['message_id']},
        ]);
        $this->scheduleFollowup($contact);
        $contact->save();
    }

    /**
     * Вопрос от Александра после хорошей оценки — один на человека. Текст —
     * по последней хорошей оценке: «Отлично» сменили на «Хорошо» — уйдёт
     * «почему не отлично?»; на «Плохо» — не уйдёт ничего.
     */
    private function sendQuestion(TelegramContact $contact): void
    {
        if ($contact->question_sent_at !== null || $contact->blocked_at) {
            return;
        }

        $latest = null;
        foreach ([false, true] as $bonus) {
            $rating = $contact->slot($bonus, 'rating');
            $at = $contact->slot($bonus, 'rated_at');
            if (TelegramContact::isGoodRating($rating) && ($latest === null || $at->gt($latest[1]))) {
                $latest = [$rating, $at];
            }
        }
        if ($latest === null) {
            return;
        }

        // Человек уже сам написал или ему ответили — шаблонный вопрос
        // невпопад, дальше разговор ведёт живой человек.
        $since = fn (?CarbonInterface $t) => $t !== null && $t->gte($latest[1]);
        if ($since($contact->last_user_message_at) || $since($contact->last_admin_message_at)) {
            $contact->forceFill(['question_sent_at' => now()])->save();

            return;
        }

        $this->sendHuman($contact, $latest[0] === TelegramContact::RATING_GREAT ? 'question_great' : 'question_good');
        $contact->question_sent_at = now();
        $this->scheduleFollowup($contact);
        $contact->save();
    }

    /** Через сутки — второе касание (если к тому времени человек так и молчит). */
    private function scheduleFollowup(TelegramContact $contact): void
    {
        if ($contact->followup_sent_at === null) {
            $contact->followup_due_at = ReminderSchedule::inWindow(now()->addHours(config('telegram_funnel.timing.followup_after_hours')));
        }
    }

    /**
     * Второе касание: досмотрел урок или оценил хорошо, но за сутки после
     * «Как вам урок?» / вопроса Александра так ничего и не написал. Одно на
     * человека и только если с ним ещё никто не разговаривал: написал сам,
     * ответил админ, «Плохо», заявка с сайта — не отправляем.
     */
    private function sendFollowup(TelegramContact $contact): void
    {
        $bad = $contact->rating === TelegramContact::RATING_BAD || $contact->bonus_rating === TelegramContact::RATING_BAD;
        $good = TelegramContact::isGoodRating($contact->rating) || TelegramContact::isGoodRating($contact->bonus_rating);

        if ($contact->followup_sent_at !== null || $contact->blocked_at || $bad
            || $contact->first_user_message_at !== null || $contact->first_admin_message_at !== null
            || ! ($contact->watchedAny() || $good)
            || SiteLead::where('telegram_contact_id', $contact->id)->exists()) {
            return;
        }

        $this->sendHuman($contact, 'followup');
        $contact->forceFill(['followup_sent_at' => now()])->save();
    }

    /**
     * Открыл урок и ушёл, ни один не досмотрев — одно напоминание на
     * человека, по уроку, который смотрел больше.
     */
    private function sendNudge(TelegramContact $contact): void
    {
        if ($contact->nudge_sent_at !== null || ! $contact->automationAllowed() || $contact->watchedAny()) {
            return;
        }

        $opened = array_values(array_filter([false, true], fn ($bonus) => $contact->slot($bonus, 'opened_at') !== null));
        if ($opened === []) {
            return; // не открывал ничего — это забота суточного напоминания
        }
        usort($opened, fn ($a, $b) => $contact->slot($b, 'progress') <=> $contact->slot($a, 'progress'));
        $bonus = $opened[0];

        if ((int) $contact->slot($bonus, 'progress') < config('telegram_funnel.timing.partial_percent')) {
            $this->sendHuman($contact, 'reminder');
        } else {
            $this->sendRateAsk($contact, $bonus, true);
        }

        // После отправки, а не до: упади она — повтор задачи отправит снова.
        // Суточное «получилось посмотреть?» после этого уже лишнее.
        $contact->forceFill(['nudge_sent_at' => now(), 'reminder_sent_at' => now(), 'reminder_due_at' => null])->save();
    }

    /** Не открыл ни один урок за сутки — «Получилось посмотреть урок?». */
    private function sendReminder(TelegramContact $contact): void
    {
        if (! $contact->automationAllowed() || $contact->reminder_sent_at !== null
            || $contact->opened_lesson_at !== null || $contact->opened_bonus_at !== null) {
            return;
        }

        $this->sendHuman($contact, 'reminder');
        $contact->forceFill(['reminder_sent_at' => now()])->save();
    }

    /**
     * «Человеческое» сообщение: «печатает…» столько, сколько живой человек
     * набирал бы этот текст (Telegram гасит индикатор через 5 с — продлеваем),
     * потом текст с подписью.
     */
    private function sendHuman(TelegramContact $contact, string $key): void
    {
        $html = FunnelTexts::render($key, $this->vars($contact));
        $t = config('telegram_funnel.timing');
        $length = mb_strlen(strip_tags($html));
        $remaining = (int) max($t['typing_seconds_min'], min($t['typing_seconds_max'], ceil($length / $t['typing_chars_per_second'])));

        try {
            while ($remaining > 0) {
                $this->api->sendTyping($contact->telegram_user_id);
                $step = min(4, $remaining);
                sleep($step);
                $remaining -= $step;
            }
        } catch (TelegramApiException) {
            // без «печатает…» сообщение всё равно уйдёт (или упадёт ниже с той же ошибкой)
        }

        $this->sendAuto($contact, $key, $html);
    }

    /**
     * Отправка сообщения человеку + запись в историю + копия в тему.
     *
     * @return array|null отправленное сообщение или null, если бот заблокирован
     */
    private function sendAuto(TelegramContact $contact, string $step, string $html, array $extra = []): ?array
    {
        if ($contact->blocked_at) {
            return null;
        }

        try {
            try {
                $sent = $this->api->sendMessage($contact->telegram_user_id, $html, $extra);
            } catch (TelegramApiException $e) {
                // Премиум-эмодзи ({e:…} в тексте из админки), которые Telegram
                // боту не разрешает, валят отправку целиком — человек остался
                // бы без урока. Повторяем с обычными эмодзи-заменителями.
                if ($e->errorCode !== 400 || ! str_contains($html, '<tg-emoji')) {
                    throw $e;
                }
                Log::warning('Telegram funnel: премиум-эмодзи отклонены, отправлено с обычными', ['step' => $step, 'error' => $e->getMessage()]);
                $html = preg_replace('~<tg-emoji emoji-id="\d+">(.*?)</tg-emoji>~su', '$1', $html);
                $sent = $this->api->sendMessage($contact->telegram_user_id, $html, $extra);
            }
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

    /** Подстановки текстов; {lesson} — урок этого сообщения (по умолчанию основной). */
    private function vars(TelegramContact $contact, ?string $lessonCode = null): array
    {
        return [
            'lesson' => FunnelLessons::title($lessonCode ?? $contact->lesson),
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
