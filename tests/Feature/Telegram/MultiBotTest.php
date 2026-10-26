<?php

namespace Tests\Feature\Telegram;

use App\Jobs\Telegram\HandleTelegramUpdate;
use App\Jobs\Telegram\RunFunnelStep;
use App\Models\TelegramContact;
use App\Service\Telegram\FunnelBot;
use App\Service\Telegram\TelegramBots;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Несколько ботов (TelegramBots): у каждого свой webhook и секрет, контакт —
 * человек в конкретном боте, в общей админ-группе бот отвечает только в
 * темах своих контактов.
 */
class MultiBotTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_CHAT = -1001234567890;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram_funnel.bot_token' => 'lesson-token',
            'services.telegram_funnel.webhook_secret' => 'lesson-secret',
            'services.telegram_funnel.admin_chat_id' => (string) self::ADMIN_CHAT,
            'services.telegram_diag.bot_token' => 'diag-token',
            'services.telegram_diag.webhook_secret' => 'diag-secret',
        ]);

        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);
    }

    public function test_unknown_bot_is_404(): void
    {
        $this->postJson('/api/telegram/webhook/nope', ['update_id' => 1], ['X-Telegram-Bot-Api-Secret-Token' => 'lesson-secret'])
            ->assertNotFound();
    }

    public function test_each_bot_checks_its_own_secret(): void
    {
        Queue::fake();

        $this->postJson('/api/telegram/webhook/diag', ['update_id' => 1], ['X-Telegram-Bot-Api-Secret-Token' => 'lesson-secret'])
            ->assertForbidden();
        $this->postJson('/api/telegram/webhook', ['update_id' => 1], ['X-Telegram-Bot-Api-Secret-Token' => 'diag-secret'])
            ->assertForbidden();
    }

    public function test_address_without_key_is_lesson_bot(): void
    {
        Queue::fake();

        $this->postJson('/api/telegram/webhook', $this->privateText(1, 555, 'привет'), ['X-Telegram-Bot-Api-Secret-Token' => 'lesson-secret'])
            ->assertOk();
        // Повтор того же апдейта — не второй раз.
        $this->postJson('/api/telegram/webhook', $this->privateText(1, 555, 'привет'), ['X-Telegram-Bot-Api-Secret-Token' => 'lesson-secret'])
            ->assertOk();

        Queue::assertPushed(HandleTelegramUpdate::class, 1);
        Queue::assertPushed(HandleTelegramUpdate::class, fn ($job) => $job->bot === TelegramBots::LESSON);
    }

    public function test_diag_webhook_queues_update_for_diag_bot(): void
    {
        Queue::fake();

        // Тот же update_id, что у бота воронки, — нумерация у ботов своя, не дубль.
        $this->postJson('/api/telegram/webhook', $this->privateText(1, 555, 'привет'), ['X-Telegram-Bot-Api-Secret-Token' => 'lesson-secret']);
        $this->postJson('/api/telegram/webhook/diag', $this->privateText(1, 555, 'привет'), ['X-Telegram-Bot-Api-Secret-Token' => 'diag-secret'])
            ->assertOk();

        Queue::assertPushed(HandleTelegramUpdate::class, 2);
        Queue::assertPushed(HandleTelegramUpdate::class, fn ($job) => $job->bot === TelegramBots::DIAG);
    }

    public function test_same_person_in_two_bots_is_two_contacts(): void
    {
        Queue::fake();
        $diag = $this->contact(TelegramBots::DIAG, 555);

        FunnelBot::make()->handleUpdate($this->privateText(1, 555, '/start cho_vk1'));

        $lesson = TelegramContact::forBot(TelegramBots::LESSON)->where('telegram_user_id', 555)->sole();
        $this->assertNotSame($diag->id, $lesson->id);
        $this->assertSame('cho', $lesson->lesson);
        $this->assertSame('vk1', $lesson->source);
        $this->assertNull($diag->fresh()->source, 'контакт другого бота не тронут');
        Queue::assertPushed(RunFunnelStep::class, fn ($job) => $job->contactId === $lesson->id && $job->step === 'welcome');
        Queue::assertNotPushed(RunFunnelStep::class, fn ($job) => $job->contactId === $diag->id);
    }

    public function test_private_message_goes_to_contact_of_this_bot(): void
    {
        Queue::fake();
        $diag = $this->contact(TelegramBots::DIAG, 555);
        $lesson = $this->contact(TelegramBots::LESSON, 555);

        FunnelBot::make()->handleUpdate($this->privateText(1, 555, 'вопрос'));

        $this->assertSame(1, $lesson->fresh()->user_messages_count);
        $this->assertSame(0, $diag->fresh()->user_messages_count);
    }

    public function test_admin_reply_in_other_bots_topic_is_ignored(): void
    {
        Queue::fake();
        $diag = $this->contact(TelegramBots::DIAG, 555, ['topic_id' => 70]);
        $lesson = $this->contact(TelegramBots::LESSON, 777, ['topic_id' => 71]);

        FunnelBot::make()->handleUpdate($this->adminTopicText(1, 70, 'ответ'));
        FunnelBot::make()->handleUpdate($this->adminTopicText(2, 71, 'ответ'));

        Queue::assertNotPushed(RunFunnelStep::class, fn ($job) => $job->contactId === $diag->id);
        Queue::assertPushed(RunFunnelStep::class, fn ($job) => $job->contactId === $lesson->id && $job->step === 'admin_reply');
    }

    public function test_step_runs_in_scenario_of_contacts_bot(): void
    {
        $diag = $this->contact(TelegramBots::DIAG, 555);

        (new RunFunnelStep($diag->id, 'welcome'))->handle();

        // Ушло токеном бота диагностики, а не воронки.
        // Приветствие — картинкой (multipart, поэтому без проверки полей).
        Http::assertSent(fn ($r) => str_contains($r->url(), '/botdiag-token/sendPhoto'));
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/botlesson-token/'));
    }

    public function test_reminders_go_through_each_bots_due_steps(): void
    {
        Queue::fake();
        $diag = $this->contact(TelegramBots::DIAG, 555, ['reminder_due_at' => now()->subMinute(), 'lesson_ask_due_at' => now()->subMinute()]);
        $lesson = $this->contact(TelegramBots::LESSON, 777, ['reminder_due_at' => now()->subMinute()]);

        $this->artisan('telegram:send-reminders')->assertSuccessful();

        Queue::assertPushed(RunFunnelStep::class, fn ($job) => $job->contactId === $lesson->id && $job->step === 'reminder');
        Queue::assertPushed(RunFunnelStep::class, fn ($job) => $job->contactId === $diag->id && $job->step === 'reminder');
        Queue::assertNotPushed(RunFunnelStep::class, fn ($job) => $job->contactId === $diag->id && $job->step === 'rate_ask');
        $this->assertNotNull($diag->fresh()->lesson_ask_due_at, 'поле чужого сценария не трогаем');
    }

    public function test_lesson_link_does_not_track_other_bots_contact(): void
    {
        Queue::fake();
        $diag = $this->contact(TelegramBots::DIAG, 555);

        $this->get('/go/'.$diag->link_token.'/cho')->assertRedirect();

        $this->assertNull($diag->fresh()->opened_lesson_at);
    }

    private function contact(string $bot, int $userId, array $attrs = []): TelegramContact
    {
        return TelegramContact::create($attrs + [
            'bot' => $bot,
            'telegram_user_id' => $userId,
            'first_name' => 'Тест',
            'lesson' => $bot === TelegramBots::LESSON ? 'cho' : null,
            'link_token' => Str::random(16),
            'started_at' => now(),
            'last_start_at' => now(),
        ]);
    }

    private function privateText(int $updateId, int $userId, string $text): array
    {
        return [
            'update_id' => $updateId,
            'message' => [
                'message_id' => $updateId,
                'from' => ['id' => $userId, 'is_bot' => false, 'first_name' => 'Тест'],
                'chat' => ['id' => $userId, 'type' => 'private'],
                'text' => $text,
            ],
        ];
    }

    private function adminTopicText(int $updateId, int $threadId, string $text): array
    {
        return [
            'update_id' => $updateId,
            'message' => [
                'message_id' => 100 + $updateId,
                'message_thread_id' => $threadId,
                'is_topic_message' => true,
                'from' => ['id' => 1, 'is_bot' => false, 'first_name' => 'Админ'],
                'chat' => ['id' => self::ADMIN_CHAT, 'type' => 'supergroup'],
                'text' => $text,
            ],
        ];
    }
}
