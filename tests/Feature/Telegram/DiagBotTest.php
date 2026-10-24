<?php

namespace Tests\Feature\Telegram;

use App\Jobs\Telegram\RunFunnelStep;
use App\Models\DiagnosticAttempt;
use App\Models\TelegramContact;
use App\Service\Diagnostic\AnswerChecker;
use App\Service\Diagnostic\DiagnosticRunner;
use App\Service\Diagnostic\DiagnosticStats;
use App\Service\Diagnostic\QuestionBank;
use App\Service\Telegram\DiagBot;
use App\Service\Telegram\TelegramBots;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Сценарий бота диагностики (DiagBot): очередь в тестах синхронная, так что
 * каждый шаг сразу уходит «в Telegram» — Http::fake записывает запросы.
 */
class DiagBotTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_CHAT = -1001234567890;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram_funnel.bot_token' => 'lesson-token',
            'services.telegram_funnel.admin_chat_id' => (string) self::ADMIN_CHAT,
            'services.telegram_diag.bot_token' => 'diag-token',
            'services.telegram_diag.webhook_secret' => 'diag-secret',
            // «Печатает…» в тестах не ждём.
            'telegram_funnel.timing.typing_seconds_min' => 0,
            'telegram_funnel.timing.typing_seconds_max' => 0,
            'diagnostic.allow_anonymous' => false,
        ]);
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 501, 'message_thread_id' => 77]])]);
    }

    public function test_start_sends_welcome_with_test_button_and_feeds_service_topic(): void
    {
        DiagBot::make()->handleUpdate($this->privateText(1, 555, '/start vk_diag1'));

        $contact = TelegramContact::forBot(TelegramBots::DIAG)->sole();
        $this->assertSame('vk_diag1', $contact->source);
        $this->assertNull($contact->lesson);
        $this->assertNotNull($contact->reminder_due_at);

        Http::assertSent(fn (Request $r) => $this->to($r, 'sendMessage') && $r['chat_id'] === 555
            && str_contains($r['text'], 'диагностика')
            && ($r['reply_markup']['inline_keyboard'][0][0]['web_app']['url'] ?? null) === route('diag.page'));
        Http::assertSent(fn (Request $r) => $this->to($r, 'createForumTopic') && $r['name'] === '🧪 Диагностики');
        Http::assertSent(fn (Request $r) => $this->to($r, 'sendMessage') && $r['chat_id'] === self::ADMIN_CHAT
            && str_contains($r['text'], '🆕') && str_contains($r['text'], 'vk_diag1'));
        // Всё — токеном бота диагностики.
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'lesson-token'));
    }

    public function test_reminder_only_if_test_not_opened(): void
    {
        $opened = $this->contact(555);
        $this->runner()->start($opened, 'miniapp');
        $silent = $this->contact(777);

        $this->step($opened, 'reminder');
        $this->step($silent, 'reminder');

        Http::assertNotSent(fn (Request $r) => $this->to($r, 'sendMessage') && $r['chat_id'] === 555);
        Http::assertSent(fn (Request $r) => $this->to($r, 'sendMessage') && $r['chat_id'] === 777
            && str_contains($r['text'], 'Александр Полтавский') && str_contains($r['text'], 'Получилось пройти диагностику')
            && isset($r['reply_markup']['inline_keyboard'][0][0]['web_app']));
        $this->assertNotNull($silent->fresh()->reminder_sent_at);
    }

    public function test_abandoned_test_gets_one_nudge_with_block_number(): void
    {
        $contact = $this->contact(555);
        $this->openSession(555);
        $state = $this->callApi('profile', ['grade' => '11'])->json();
        $this->callApi('answer', ['question_id' => $state['question']['id'], 'answer' => ['x' => 1]]);

        $this->assertNotNull($contact->fresh()->nudge_due_at, 'отсчёт «бросил» — от последнего ответа');

        $this->step($contact, 'nudge');
        $this->step($contact, 'nudge');

        $this->assertSame(1, $this->countSent('sendMessage', fn ($r) => $r['chat_id'] === 555), 'одно напоминание');
        Http::assertSent(fn (Request $r) => $this->to($r, 'sendMessage') && $r['chat_id'] === 555
            && str_contains($r['text'], 'блоке 1 из 5') && isset($r['reply_markup']['inline_keyboard'][0][0]['web_app']));
    }

    public function test_finish_sends_result_with_buttons_and_yes_in_chat_opens_hot_topic(): void
    {
        $contact = $this->contact(555);
        $attempt = $this->finishViaApi(555);

        $this->assertSame(501, (int) $attempt->bot_message_id);
        $this->assertNotNull($contact->fresh()->followup_due_at, 'не ответит на «Хочешь улучшить?» — касание через сутки');
        Http::assertSent(fn (Request $r) => $this->to($r, 'sendMessage') && $r['chat_id'] === 555
            && str_contains($r['text'], 'Твой результат') && str_contains($r['text'], 'Хочешь улучшить')
            && ($r['reply_markup']['inline_keyboard'][0][0]['callback_data'] ?? null) === 'diag:yes:'.$attempt->id);
        Http::assertSent(fn (Request $r) => $this->to($r, 'sendMessage') && $r['chat_id'] === self::ADMIN_CHAT && str_contains($r['text'], '✅'));

        DiagBot::make()->handleUpdate($this->buttonPress(555, 'diag:yes:'.$attempt->id));

        $attempt->refresh();
        $this->assertSame('yes', $attempt->improve);
        $this->assertSame('bot', $attempt->improve_source);
        $contact->refresh();
        $this->assertNotNull($contact->question_due_at);
        $this->assertNull($contact->followup_due_at);
        $this->assertSame(77, (int) $contact->topic_id);

        Http::assertSent(fn (Request $r) => $this->to($r, 'editMessageText') && str_contains($r['text'], '✅ Ответ: Да, хочу')
            && $r['reply_markup']['inline_keyboard'] === []);
        Http::assertSent(fn (Request $r) => $this->to($r, 'sendMessage') && $r['chat_id'] === 555 && str_contains($r['text'], 'подключу Александра'));
        Http::assertSent(fn (Request $r) => $this->to($r, 'createForumTopic') && str_starts_with($r['name'], '🔥 🧪 '));
        Http::assertSent(fn (Request $r) => $this->to($r, 'sendMessage') && ($r['message_thread_id'] ?? null) === 77
            && str_contains($r['text'], 'диагностика') && str_contains($r['text'], 'Сейчас ~'));
        Http::assertSent(fn (Request $r) => $this->to($r, 'sendMessage') && ($r['message_thread_id'] ?? null) === 77
            && str_contains($r['text'], 'Хочет улучшить') && $r['disable_notification'] === false);

        // Второе нажатие — ничего не меняет.
        DiagBot::make()->handleUpdate($this->buttonPress(555, 'diag:no:'.$attempt->id));
        $this->assertSame('yes', $attempt->fresh()->improve);
    }

    public function test_question_after_yes_then_one_followup(): void
    {
        $contact = $this->contact(555);
        $attempt = $this->finishViaApi(555);
        $this->callApi('improve', ['yes' => true]);

        // Ответил на странице — кнопки под итогом в чате убираем.
        Http::assertSent(fn (Request $r) => $this->to($r, 'editMessageReplyMarkup') && $r['message_id'] === (int) $attempt->bot_message_id);

        $this->step($contact, 'question');
        Http::assertSent(fn (Request $r) => $this->to($r, 'sendMessage') && $r['chat_id'] === 555
            && str_contains($r['text'], 'какая тема в обществознании сейчас даётся сложнее всего'));
        $contact->refresh();
        $this->assertNotNull($contact->question_sent_at);
        $this->assertNotNull($contact->followup_due_at);

        $this->step($contact, 'followup');
        $this->step($contact, 'followup');
        $this->assertSame(1, $this->countSent('sendMessage', fn ($r) => $r['chat_id'] === 555 && str_contains($r['text'], 'подскажу, с чего начать')));
    }

    public function test_no_gets_soft_reply_and_later_one_touch(): void
    {
        $contact = $this->contact(555);
        $this->finishViaApi(555);
        $this->callApi('improve', ['yes' => false]);

        Http::assertSent(fn (Request $r) => $this->to($r, 'sendMessage') && $r['chat_id'] === 555 && str_contains($r['text'], 'Результат сохранился'));
        $this->assertTrue($contact->fresh()->followup_due_at->gt(now()->addDay()), 'касание — через 2 дня');
        $this->assertNull($contact->fresh()->question_due_at);

        $this->step($contact, 'followup');
        Http::assertSent(fn (Request $r) => $this->to($r, 'sendMessage') && $r['chat_id'] === 555 && str_contains($r['text'], 'Как подготовка?'));
    }

    public function test_no_answer_to_improve_gets_the_question_once(): void
    {
        $contact = $this->contact(555);
        $this->finishViaApi(555);

        $this->step($contact, 'followup');
        $this->step($contact, 'question');

        $this->assertSame(1, $this->countSent('sendMessage', fn ($r) => $r['chat_id'] === 555 && str_contains($r['text'], 'даётся сложнее всего')));
        $this->assertNotNull($contact->fresh()->question_sent_at);
    }

    public function test_automation_stops_once_person_wrote(): void
    {
        $contact = $this->contact(555);
        $this->finishViaApi(555);
        DiagBot::make()->handleUpdate($this->privateText(9, 555, 'а сколько стоит курс?'));

        $this->step($contact, 'followup');

        $this->assertSame(0, $this->countSent('sendMessage', fn ($r) => $r['chat_id'] === 555 && str_contains($r['text'], 'Александр Полтавский')));
        Http::assertSent(fn (Request $r) => $this->to($r, 'forwardMessage') && $r['from_chat_id'] === 555);
    }

    public function test_admin_reply_relayed_only_in_own_topics_and_commands_ignored(): void
    {
        $diag = $this->contact(555, ['topic_id' => 70]);
        $lesson = TelegramContact::create([
            'bot' => TelegramBots::LESSON, 'telegram_user_id' => 777, 'lesson' => 'cho', 'link_token' => Str::random(16),
            'topic_id' => 71, 'started_at' => now(), 'last_start_at' => now(),
        ]);

        DiagBot::make()->handleUpdate($this->adminTopicText(1, 70, 'Привет! Давай разберём право'));
        DiagBot::make()->handleUpdate($this->adminTopicText(2, 71, 'это не наша тема'));
        DiagBot::make()->handleUpdate($this->adminTopicText(3, 70, '/emoji'));

        Http::assertSent(fn (Request $r) => $this->to($r, 'copyMessage') && $r['chat_id'] === 555);
        Http::assertNotSent(fn (Request $r) => $this->to($r, 'copyMessage') && $r['chat_id'] === 777);
        $this->assertSame(1, $this->countSent('copyMessage'));
        $this->assertNotNull($diag->fresh()->first_admin_message_at);
        $this->assertNull($lesson->fresh()->first_admin_message_at);
    }

    public function test_daily_digest_has_diagnostic_line(): void
    {
        $this->contact(555);

        $this->artisan('telegram:digest')->assertSuccessful();

        // Итог дня шлёт бот воронки — в общую группу.
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/botlesson-token/sendMessage')
            && str_contains($r['text'], '🧪 Диагностика: запустили 1 · прошли 0'));
    }

    public function test_mini_app_opened_by_direct_link_keeps_ad_label(): void
    {
        $this->postJson('/diag/api/session', ['init_data' => $this->initData(4242, ['start_param' => 'tiktok_1'])])->assertOk();

        $this->assertSame('tiktok_1', TelegramContact::forBot(TelegramBots::DIAG)->where('telegram_user_id', 4242)->sole()->source);
    }

    public function test_waiting_diag_lead_alerts_admin_after_15_minutes_lesson_after_2_hours(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        $waiting = ['topic_id' => 77, 'last_user_message_at' => now()->subMinutes(20)];
        $this->contact(601, $waiting);
        TelegramContact::create(['topic_id' => 78] + $waiting + ['bot' => TelegramBots::LESSON, 'lesson' => 'cho', 'telegram_user_id' => 602,
            'link_token' => Str::random(16), 'started_at' => now(), 'last_start_at' => now()]);

        $this->artisan('telegram:remind-unanswered')->assertSuccessful();
        $this->artisan('telegram:remind-unanswered')->assertSuccessful();

        $alert = fn (Request $r) => str_contains((string) ($r['text'] ?? ''), 'Ждёт ответа уже 20 мин');
        $this->assertSame(1, $this->countSent('sendMessage', $alert), 'диагностика — через 15 мин, один раз');
        $this->assertSame(0, Http::recorded(fn (Request $r) => str_contains($r->url(), 'lesson-token'))->count(), 'урок — только через 2 ч');
    }

    public function test_drop_offs_by_stage_and_question(): void
    {
        $this->openSession(701);                    // бросил на анкете
        $this->openSession(702);                    // бросил на втором вопросе первого блока
        $state = $this->callApi('profile', ['grade' => '11'])->json();
        $bank = QuestionBank::fromConfig();
        $state = $this->callApi('answer', ['question_id' => $state['question']['id'],
            'answer' => AnswerChecker::solution($bank->get($state['question']['id']))])->json('state');
        $this->openSession(703);                    // ещё проходит — не брошено

        $attempt = DiagnosticAttempt::where('token', $this->attemptToken)->sole();
        DiagnosticAttempt::where('id', '!=', $attempt->id)->update(['updated_at' => now()->subHours(2)]);

        $stats = new DiagnosticStats();
        $drop = DiagnosticStats::dropOffs($stats->contacts());
        $stages = collect($drop['stages'])->keyBy('label');
        $first = DiagnosticAttempt::whereHas('contact', fn ($q) => $q->where('telegram_user_id', 702))->sole();

        $this->assertSame(2, $drop['total']);
        $this->assertSame(1, $stages['Анкета (класс)']['n']);
        $this->assertSame([2 => 1], $stages[config('diagnostic.blocks.'.$first->block_order[0].'.title')]['positions']);
        $this->assertSame([$state['question']['id'] => 1], $drop['questions']);
        $this->assertContains($state['question']['id'], array_column($stats->questions($stats->contacts(), array_keys($drop['questions'])), 'id'));
    }

    // ---------- помощники ----------

    private ?string $attemptToken = null;

    private function contact(int $userId, array $attrs = []): TelegramContact
    {
        return TelegramContact::create($attrs + [
            'bot' => TelegramBots::DIAG,
            'telegram_user_id' => $userId,
            'first_name' => 'Аня',
            'link_token' => Str::random(16),
            'started_at' => now(),
            'last_start_at' => now(),
        ]);
    }

    private function runner(): DiagnosticRunner
    {
        return DiagnosticRunner::make();
    }

    private function step(TelegramContact $contact, string $step, array $payload = []): void
    {
        (new RunFunnelStep($contact->id, $step, $payload))->handle();
    }

    private function openSession(int $userId): void
    {
        $this->attemptToken = $this->postJson('/diag/api/session', ['init_data' => $this->initData($userId)])->assertOk()->json('attempt');
    }

    private function callApi(string $action, array $body)
    {
        return $this->postJson('/diag/api/'.$action, $body, ['X-Diag-Attempt' => $this->attemptToken]);
    }

    private function finishViaApi(int $userId): DiagnosticAttempt
    {
        $this->openSession($userId);
        $state = $this->callApi('profile', ['grade' => '11'])->json();
        $bank = QuestionBank::fromConfig();
        while ($state['screen'] === 'question') {
            $state = $this->callApi('answer', ['question_id' => $state['question']['id'],
                'answer' => AnswerChecker::solution($bank->get($state['question']['id']))])->json('state');
        }
        $this->callApi('target', ['target' => 80])->assertJsonPath('screen', 'result');

        return DiagnosticAttempt::where('token', $this->attemptToken)->sole();
    }

    private function to(Request $r, string $method): bool
    {
        return str_ends_with($r->url(), '/bot'.'diag-token/'.$method);
    }

    private function countSent(string $method, ?callable $filter = null): int
    {
        return Http::recorded(fn (Request $r) => $this->to($r, $method) && (! $filter || $filter($r)))->count();
    }

    private function privateText(int $updateId, int $userId, string $text): array
    {
        return ['update_id' => $updateId, 'message' => [
            'message_id' => $updateId,
            'from' => ['id' => $userId, 'is_bot' => false, 'first_name' => 'Аня'],
            'chat' => ['id' => $userId, 'type' => 'private'],
            'text' => $text,
        ]];
    }

    private function adminTopicText(int $updateId, int $threadId, string $text): array
    {
        return ['update_id' => $updateId, 'message' => [
            'message_id' => 100 + $updateId,
            'message_thread_id' => $threadId,
            'is_topic_message' => true,
            'from' => ['id' => 1, 'is_bot' => false, 'first_name' => 'Админ'],
            'chat' => ['id' => self::ADMIN_CHAT, 'type' => 'supergroup'],
            'text' => $text,
        ]];
    }

    private function buttonPress(int $userId, string $data): array
    {
        return ['update_id' => random_int(1000, 9999), 'callback_query' => [
            'id' => 'cb1',
            'from' => ['id' => $userId, 'is_bot' => false],
            'data' => $data,
            'message' => ['message_id' => 501, 'chat' => ['id' => $userId, 'type' => 'private'], 'text' => 'Твой результат: …'],
        ]];
    }

    private function initData(int $userId, array $extra = []): string
    {
        $fields = $extra + [
            'auth_date' => (string) time(),
            'user' => json_encode(['id' => $userId, 'first_name' => 'Аня'], JSON_UNESCAPED_UNICODE),
        ];
        ksort($fields);
        $check = implode("\n", array_map(fn ($k, $v) => "{$k}={$v}", array_keys($fields), $fields));
        $fields['hash'] = hash_hmac('sha256', $check, hash_hmac('sha256', 'diag-token', 'WebAppData', true));

        return http_build_query($fields);
    }
}
