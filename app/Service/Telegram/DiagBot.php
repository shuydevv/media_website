<?php

namespace App\Service\Telegram;

use App\Jobs\Telegram\RunFunnelStep;
use App\Models\DiagnosticAttempt;
use App\Models\TelegramContact;
use App\Models\TelegramMessage;
use App\Service\Diagnostic\DiagnosticRunner;
use App\Service\Diagnostic\DiagnosticSummary;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Сценарий бота diag — диагностика по обществознанию (ТЗ —
 * docs/diagnostic/, раздел 8; тексты — config/telegram_diag.php):
 *
 *   /start <метка> → приветствие с кнопкой web_app «Пройти диагностику»
 *     (тест — Mini App, Diagnostic\DiagnosticController);
 *   не открыл тест за 22–26 ч → «Получилось пройти?» от Александра;
 *   начал и бросил → через 3 ч «Продолжим?» с кнопкой;
 *   закончил → итог в чат с «Хочешь улучшить свои знания?» [Да] [Пока нет]
 *     (кнопок нет, если уже ответил на странице) и строка в «🧪 Диагностики»;
 *   «Да» → «Сейчас подключу Александра», тема в админ-группе со сводкой
 *     (со звуком — горячий), через 1–4 мин мягкий вопрос от Александра;
 *     молчит сутки → одно второе касание;
 *   «Пока нет» → автоответ без давления, через 2 дня одно мягкое касание;
 *   не ответил на вопрос сутки → тот же вопрос от Александра.
 *
 * Занятие бот не предлагает: это делает админ в живом диалоге. Как только
 * человек написал сам или ему ответил админ — напоминания не уходят
 * (TelegramContact::automationAllowed()). Отложенное — полями *_due_at
 * (DUE_STEPS), условия проверяются в момент отправки, как у FunnelBot.
 *
 * В общей админ-группе этот бот отвечает только в темах своих контактов;
 * команды (/chatid, /emoji) — забота бота воронки.
 */
class DiagBot implements BotScenario
{
    public const BOT = TelegramBots::DIAG;

    public const DUE_STEPS = [
        'reminder_due_at' => ['reminder', []],
        'nudge_due_at' => ['nudge', []],
        'question_due_at' => ['question', []],
        'followup_due_at' => ['followup', []],
    ];

    public function __construct(
        private readonly TelegramApi $api,
        private readonly AdminTopics $topics,
        private readonly ContactMessenger $messenger,
    ) {
    }

    public static function make(): self
    {
        $api = TelegramApi::forBot(self::BOT);
        $topics = new AdminTopics($api);

        return new self($api, $topics, new ContactMessenger($api, $topics));
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

        $message = $update['message'] ?? null;
        if (! $message || ! isset($message['chat']['id'])) {
            return;
        }

        if (($message['chat']['type'] ?? null) === 'private') {
            $this->handlePrivateMessage($message);
        } elseif (AdminTopics::isAdminChat($message['chat']['id'])) {
            $this->handleAdminChatMessage($message);
        }
    }

    public function runStep(TelegramContact $contact, string $step, array $payload = []): void
    {
        $attempt = isset($payload['attempt_id'])
            ? DiagnosticAttempt::where('telegram_contact_id', $contact->id)->find($payload['attempt_id'])
            : null;

        match ($step) {
            'welcome' => $this->sendWelcome($contact),
            'reminder' => $this->sendReminder($contact),
            'nudge' => $this->sendNudge($contact),
            'result' => $attempt ? $this->sendResult($contact, $attempt) : null,
            'close_result' => $attempt?->bot_message_id ? $this->messenger->closeButtons($contact->telegram_user_id, (int) $attempt->bot_message_id) : null,
            'yes_auto' => $this->messenger->sendAuto($contact, 'diag_yes_auto', FunnelTexts::render('diag_yes_auto', $this->vars($contact))),
            'no_auto' => $this->messenger->sendAuto($contact, 'diag_no_auto', FunnelTexts::render('diag_no_auto', $this->vars($contact))),
            'question' => $this->sendQuestion($contact),
            'followup' => $this->sendFollowup($contact),
            'hot_topic' => $attempt ? $this->openHotTopic($contact, $attempt) : null,
            'feed_start' => $this->postFeed($contact, '🆕 '.e($contact->displayName())
                .($contact->username && ! str_starts_with($contact->displayName(), '@') ? ' @'.e($contact->username) : '')
                .' · '.($contact->source ? '<code>'.e($contact->source).'</code>' : 'без метки')),
            'feed_finished' => $attempt ? $this->postFeed($contact, DiagnosticSummary::feedLine($contact, $attempt)) : null,
            'forward' => $this->topics->forward($contact, (int) $payload['message_id']),
            'note' => $this->topics->note($contact, $payload['html'], $payload['silent'] ?? true),
            'admin_reply' => $this->topics->relayAdminMessage($contact, $payload['message']),
            'refresh_status' => $this->topics->refreshStatus($contact),
            default => Log::warning("Telegram diag: неизвестный шаг {$step}"),
        };
    }

    // ---------- события теста (Diagnostic\DiagnosticController) ----------

    /** Открыл Mini App; $started — началось новое прохождение. */
    public function opened(TelegramContact $contact, DiagnosticAttempt $attempt, bool $started): void
    {
        $contact->forceFill(['reminder_due_at' => null])->save();
        if (! $started) {
            return;
        }

        $first = ! $contact->diagnosticAttempts()->where('id', '!=', $attempt->id)->exists();
        $this->dispatch($contact, 'note', ['html' => $first ? '👀 Открыл диагностику' : '🔁 Начал диагностику заново']);
        $this->dispatch($contact, 'refresh_status');
    }

    /** Ответил на вопрос: «начал и бросил» отсчитываем от последнего ответа. */
    public function answered(TelegramContact $contact, DiagnosticAttempt $attempt): void
    {
        if ($attempt->isFinished() || $contact->nudge_sent_at !== null) {
            return;
        }

        $contact->forceFill([
            'nudge_due_at' => ReminderSchedule::inWindow(now()->addHours(config('telegram_diag.timing.nudge_after_hours'))),
        ])->save();
    }

    public function finished(TelegramContact $contact, DiagnosticAttempt $attempt): void
    {
        $contact->nudge_due_at = null;
        // Не ответит на «Хочешь улучшить?» — через сутки тот же вопрос, что после «Да».
        if ($attempt->improve === null && $contact->followup_sent_at === null && $contact->question_sent_at === null) {
            $contact->followup_due_at = ReminderSchedule::inWindow(now()->addHours(config('telegram_diag.timing.unanswered_after_hours')));
        }
        $contact->save();

        $this->dispatch($contact, 'result', ['attempt_id' => $attempt->id]);
        $this->dispatch($contact, 'feed_finished', ['attempt_id' => $attempt->id]);
        $this->dispatch($contact, 'note', ['html' => '✅ Прошёл диагностику'.($attempt->isBasic() ? ' (🌱 начальный)' : '').': ~'.$attempt->result['low'].'–'.$attempt->result['high']]);
        $this->dispatch($contact, 'refresh_status');
    }

    /** Первый ответ на «Хочешь улучшить свои знания?» — на странице ($source = page) или кнопкой в чате (bot). */
    public function improved(TelegramContact $contact, DiagnosticAttempt $attempt, bool $yes, string $source): void
    {
        if ($yes) {
            if ($contact->question_sent_at === null && $contact->question_due_at === null) {
                $t = config('telegram_funnel.timing');
                $contact->question_due_at = ReminderSchedule::inWindow(
                    now()->addSeconds(random_int($t['question_delay_min_seconds'], $t['question_delay_max_seconds']))
                );
            }
            // Второе касание — от вопроса Александра (sendQuestion).
            $contact->followup_due_at = null;
            $this->dispatch($contact, 'yes_auto');
            $this->dispatch($contact, 'hot_topic', ['attempt_id' => $attempt->id]);
        } else {
            if ($contact->followup_sent_at === null) {
                $contact->followup_due_at = ReminderSchedule::inWindow(now()->addHours(config('telegram_diag.timing.no_followup_after_hours')));
            }
            $this->dispatch($contact, 'no_auto');
            $this->dispatch($contact, 'note', ['html' => '🙅 «Пока нет» на «Хочешь улучшить?»']);
        }
        $contact->save();

        if ($source === 'page') {
            $this->dispatch($contact, 'close_result', ['attempt_id' => $attempt->id]);
        }
        $this->dispatch($contact, 'refresh_status');
    }

    // ---------- апдейты ----------

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

        // Пишет без /start (открыл Mini App из меню, потом написал) — контакт
        // заводим молча: человек уже разговаривает.
        $contact = TelegramContact::forBot(self::BOT)->where('telegram_user_id', $from['id'])->first()
            ?? $this->createContact($from, null);

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
        $source = StartParam::parse($param, [], '')['source'];
        $contact = TelegramContact::forBot(self::BOT)->where('telegram_user_id', $from['id'])->first();

        if (! $contact) {
            $contact = $this->createContact($from, $source);
        } else {
            // Метка — по первому касанию; обновляем только имя и время.
            $contact->forceFill([
                'username' => $from['username'] ?? null,
                'first_name' => $from['first_name'] ?? null,
                'last_name' => $from['last_name'] ?? null,
                'last_start_at' => now(),
                'blocked_at' => null,
            ])->save();
            $this->dispatch($contact, 'note', ['html' => '🔁 Повторный /start'.($param ? ' (<code>'.e($param).'</code>)' : '')]);
        }

        $this->dispatch($contact, 'welcome');
    }

    private function createContact(array $from, ?string $source): TelegramContact
    {
        $now = now();
        $contact = TelegramContact::create([
            'bot' => self::BOT,
            'telegram_user_id' => $from['id'],
            'username' => $from['username'] ?? null,
            'first_name' => $from['first_name'] ?? null,
            'last_name' => $from['last_name'] ?? null,
            'source' => $source,
            'link_token' => Str::random(16),
            'started_at' => $now,
            'last_start_at' => $now,
            'reminder_due_at' => ReminderSchedule::randomFor($now),
        ]);

        // Строка в «🧪 Диагностики» — без звука; тема человека — когда он
        // напишет сам или ответит «Да, хочу».
        $this->dispatch($contact, 'feed_start');

        return $contact;
    }

    /** Кнопки «Да, хочу» / «Пока нет» под итогом: diag:yes:<прохождение>. */
    private function handleCallback(array $callback): void
    {
        $data = $callback['data'] ?? '';
        $contact = TelegramContact::forBot(self::BOT)->where('telegram_user_id', $callback['from']['id'] ?? 0)->first();
        $attempt = $contact && preg_match('/^diag:(yes|no):(\d+)$/', $data, $m)
            ? DiagnosticAttempt::where('telegram_contact_id', $contact->id)->find((int) $m[2])
            : null;
        if (! $attempt) {
            $this->messenger->answerCallback($callback['id']);

            return;
        }

        $yes = $m[1] === 'yes';
        $recorded = DiagnosticRunner::make()->improve($attempt, $yes, 'bot');
        $this->messenger->answerCallback($callback['id'], $recorded ? 'Готово!' : 'Ответ уже есть — спасибо!');

        $message = $callback['message'] ?? null;
        if ($message && isset($message['message_id'], $message['chat']['id'])) {
            $label = FunnelTexts::plain($attempt->improve === 'yes' ? 'diag_btn_yes' : 'diag_btn_no');
            $this->messenger->closeButtons($message['chat']['id'], (int) $message['message_id'], $message, '✅ Ответ: '.$label);
        }

        if ($recorded) {
            $this->improved($contact, $attempt, $yes, 'bot');
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

    /** Ответ админа в теме своего контакта → человеку. Остальное в группе — не наше. */
    private function handleAdminChatMessage(array $message): void
    {
        $threadId = $message['message_thread_id'] ?? null;
        $from = $message['from'] ?? [];
        if (! $threadId || ! ($message['is_topic_message'] ?? false) || ($from['is_bot'] ?? false)
            || isset($message['forum_topic_created']) || isset($message['forum_topic_edited'])) {
            return;
        }

        $body = ltrim($message['text'] ?? $message['caption'] ?? '');
        // «//» — заметка для себя, «/команда» — для бота, человеку не шлём.
        if (str_starts_with($body, '//') || preg_match('~^/[A-Za-z]~', $body)) {
            return;
        }

        $contact = TelegramContact::forBot(self::BOT)->where('topic_id', $threadId)->first();
        if ($contact) {
            $this->dispatch($contact, 'admin_reply', ['message' => $message]);
        }
    }

    // ---------- шаги ----------

    private function sendWelcome(TelegramContact $contact): void
    {
        $this->messenger->sendAuto($contact, 'diag_welcome', FunnelTexts::render('diag_welcome', $this->vars($contact)), [
            'reply_markup' => ['inline_keyboard' => [[$this->testButton('diag_btn_start')]]],
        ]);
    }

    /** Не открыл тест — «Получилось пройти?» от Александра, один раз. */
    private function sendReminder(TelegramContact $contact): void
    {
        if (! $contact->automationAllowed() || $contact->reminder_sent_at !== null || $contact->diagnosticAttempts()->exists()) {
            return;
        }

        $this->messenger->sendHuman($contact, 'diag_reminder', FunnelTexts::render('diag_reminder', $this->vars($contact)), [
            'reply_markup' => ['inline_keyboard' => [[$this->testButton('diag_btn_start')]]],
        ]);
        $contact->forceFill(['reminder_sent_at' => now()])->save();
    }

    /** Начал и бросил — «Продолжим?», один раз. */
    private function sendNudge(TelegramContact $contact): void
    {
        $attempt = $contact->latestDiagnostic();
        if (! $attempt || $attempt->isFinished() || $contact->nudge_sent_at !== null || ! $contact->automationAllowed()) {
            return;
        }

        $total = count($attempt->block_order);
        $vars = $this->vars($contact) + ['block' => min($attempt->block_index + 1, $total), 'total' => $total];
        $this->messenger->sendAuto($contact, 'diag_nudge', FunnelTexts::render('diag_nudge', $vars), [
            'reply_markup' => ['inline_keyboard' => [[$this->testButton('diag_btn_continue')]]],
        ]);
        $contact->forceFill(['nudge_sent_at' => now()])->save();
    }

    /** Итог в чат; кнопки ответа — если на странице ещё не ответил. */
    private function sendResult(TelegramContact $contact, DiagnosticAttempt $attempt): void
    {
        if (! $attempt->isFinished()) {
            return;
        }

        $r = $attempt->result;
        $vars = $this->vars($contact) + [
            'low' => $r['low'], 'high' => $r['high'], 'potential' => $r['potential'],
            'weak' => DiagnosticSummary::weakTitles($attempt),
            // Начальный тест — без баллов: что уже знает и что ещё не исследовано.
            'known' => DiagnosticSummary::titlesByLevel($attempt, 2, 3) ?: 'разберёмся вместе',
            'new' => DiagnosticSummary::titlesByLevel($attempt, 0, 1) ?: 'всё уже знакомо 💪',
        ];
        $extra = $attempt->improve === null ? ['reply_markup' => ['inline_keyboard' => [[
            ['text' => FunnelTexts::plain('diag_btn_yes'), 'callback_data' => 'diag:yes:'.$attempt->id],
            ['text' => FunnelTexts::plain('diag_btn_no'), 'callback_data' => 'diag:no:'.$attempt->id],
        ]]]] : [];

        $sent = $this->messenger->sendAuto($contact, 'diag_result', FunnelTexts::render($attempt->isBasic() ? 'diag_result_basic' : 'diag_result', $vars), $extra);
        if ($sent && $extra !== []) {
            $attempt->forceFill(['bot_message_id' => $sent['message_id'] ?? null])->save();
        }
    }

    /**
     * Мягкий вопрос от Александра — один на человека. Человек уже написал
     * сам или ему ответили после «Да» — шаблонный вопрос невпопад.
     */
    private function sendQuestion(TelegramContact $contact): void
    {
        if ($contact->question_sent_at !== null || $contact->blocked_at) {
            return;
        }

        $since = $contact->latestDiagnostic()?->improve_at;
        $after = fn (?CarbonInterface $t) => $t !== null && ($since === null || $t->gte($since));
        if ($after($contact->last_user_message_at) || $after($contact->last_admin_message_at)) {
            $contact->forceFill(['question_sent_at' => now()])->save();

            return;
        }

        $this->messenger->sendHuman($contact, 'diag_question', FunnelTexts::render('diag_question', $this->vars($contact)));
        $contact->question_sent_at = now();
        if ($contact->followup_sent_at === null) {
            $contact->followup_due_at = ReminderSchedule::inWindow(now()->addHours(config('telegram_diag.timing.followup_after_hours')));
        }
        $contact->save();
    }

    /**
     * Одно второе касание на человека — по тому, как он ответил на «Хочешь
     * улучшить?»: «Да» и молчит после вопроса, «Пока нет», или не ответил
     * вовсе (тогда — тот же вопрос, что после «Да»).
     */
    private function sendFollowup(TelegramContact $contact): void
    {
        $attempt = $contact->latestDiagnostic();
        if ($contact->followup_sent_at !== null || ! $contact->automationAllowed() || ! $attempt?->isFinished()) {
            return;
        }

        $key = match ($attempt->improve) {
            'yes' => 'diag_followup_yes',
            'no' => 'diag_followup_no',
            default => $contact->question_sent_at === null ? 'diag_question' : null,
        };
        if ($key === null) {
            return;
        }

        $this->messenger->sendHuman($contact, $key, FunnelTexts::render($key, $this->vars($contact)));
        $contact->forceFill(array_filter([
            'followup_sent_at' => now(),
            'question_sent_at' => $key === 'diag_question' ? now() : null,
        ]))->save();
    }

    /** «Да, хочу» — тема со звуком: горячий человек, админ начинает с конкретики. */
    private function openHotTopic(TelegramContact $contact, DiagnosticAttempt $attempt): void
    {
        $existed = (bool) $contact->topic_id;
        if (! $this->topics->ensureTopic($contact)) {
            return;
        }

        // Новая тема и так начинается с карточки со сводкой; в старую — сводку отдельно.
        $html = '🔥 <b>Хочет улучшить знания</b>'.($existed ? "\n".implode("\n", DiagnosticSummary::attemptLines($attempt)) : '');
        $this->topics->note($contact, $html, false);
        $this->topics->refreshStatus($contact);
    }

    private function postFeed(TelegramContact $contact, string $html): void
    {
        try {
            $this->topics->postToService('diag', $html, true);
        } catch (TelegramApiException $e) {
            Log::warning('Telegram diag: не удалось написать в «Диагностики»', ['contact' => $contact->id, 'error' => $e->getMessage()]);
        }
    }

    private function testButton(string $key): array
    {
        return ['text' => FunnelTexts::plain($key), 'web_app' => ['url' => route('diag.page')]];
    }

    private function vars(TelegramContact $contact): array
    {
        return ['name' => $contact->first_name ?: ''];
    }

    private function dispatch(TelegramContact $contact, string $step, array $payload = [], int $delaySeconds = 0): void
    {
        $job = RunFunnelStep::dispatch($contact->id, $step, $payload)->onQueue(config('telegram_funnel.queue'));
        if ($delaySeconds > 0) {
            $job->delay(now()->addSeconds($delaySeconds));
        }
    }
}
