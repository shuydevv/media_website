<?php

namespace Tests\Feature\Telegram;

use App\Jobs\Telegram\RunFunnelStep;
use App\Models\TelegramContact;
use App\Models\TelegramMessage;
use App\Service\Telegram\TelegramBots;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Сообщение человека должно дойти до его темы в админ-группе: задача
 * forward отмечает forwarded_at, telegram:resend-unforwarded досылает
 * неотмеченное (23.10.2026 оно осталось только в failed_jobs).
 */
class ForwardResendTest extends TestCase
{
    private const ADMIN_CHAT = -1001234567890;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram_funnel.bot_token' => 'lesson-token',
            'services.telegram_funnel.admin_chat_id' => (string) self::ADMIN_CHAT,
            'services.telegram.bot_token' => null,
        ]);
    }

    public function test_forward_step_marks_message_forwarded(): void
    {
        $this->fakeTelegram();
        $contact = $this->contact(['topic_id' => 70]);
        $message = $this->message($contact, 11);

        (new RunFunnelStep($contact->id, 'forward', ['message_id' => 11]))->handle();

        $this->assertNotNull($message->fresh()->forwarded_at);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/forwardMessage') && $r['message_thread_id'] === 70);
    }

    public function test_forward_step_creates_topic_without_cache(): void
    {
        $this->fakeTelegram();
        $contact = $this->contact();
        $this->message($contact, 11);

        (new RunFunnelStep($contact->id, 'forward', ['message_id' => 11]))->handle();

        $this->assertSame(500, (int) $contact->fresh()->topic_id);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/forwardMessage') && $r['message_thread_id'] === 500);
    }

    public function test_already_forwarded_message_is_not_forwarded_again(): void
    {
        $this->fakeTelegram();
        $contact = $this->contact(['topic_id' => 70]);
        $this->message($contact, 11, ['forwarded_at' => now()]);

        (new RunFunnelStep($contact->id, 'forward', ['message_id' => 11]))->handle();

        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/forwardMessage'));
    }

    public function test_resend_forwards_old_unforwarded_messages_only(): void
    {
        $this->fakeTelegram();
        $contact = $this->contact(['topic_id' => 70]);
        $old = $this->message($contact, 11, ['created_at' => now()->subMinutes(20)]);
        $fresh = $this->message($contact, 12, ['created_at' => now()->subMinute()]);
        $this->message($contact, 13, ['created_at' => now()->subMinutes(20), 'forwarded_at' => now()->subMinutes(19)]);

        $this->artisan('telegram:resend-unforwarded')->assertSuccessful();

        $this->assertNotNull($old->fresh()->forwarded_at);
        $this->assertNull($fresh->fresh()->forwarded_at, 'свежее — ещё за задачей очереди');
        $forwards = collect(Http::recorded())->filter(fn ($pair) => str_ends_with($pair[0]->url(), '/forwardMessage'));
        $this->assertCount(1, $forwards);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/forwardMessage') && $r['message_id'] === 11);
    }

    public function test_failed_resend_alerts_once_and_retries_later(): void
    {
        $this->fakeTelegram(forwardFails: true);
        $contact = $this->contact(['topic_id' => 70]);
        $message = $this->message($contact, 11, ['created_at' => now()->subMinutes(20)]);

        $this->artisan('telegram:resend-unforwarded')->assertSuccessful();
        $this->artisan('telegram:resend-unforwarded')->assertSuccessful(); // взято — до повтора не трогаем

        $message->refresh();
        $this->assertNull($message->forwarded_at);
        $this->assertSame(1, $message->forward_failures);
        $this->assertStringContainsString('message to forward not found', $message->forward_error);
        $this->assertNotNull($message->forward_alerted_at);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/sendMessage') && str_contains($r['text'], 'не попало в его тему'));
        $this->assertSame(1, DB::table('telegram_service_topics')->where('key', 'alerts')->count());

        $this->travel(6)->minutes();
        $this->artisan('telegram:resend-unforwarded')->assertSuccessful();
        $this->assertSame(2, $message->fresh()->forward_failures);
        $alerts = collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]['text'] ?? '', 'не попало в его тему'));
        $this->assertCount(1, $alerts, 'алерт — один раз');
    }

    public function test_after_two_failures_message_goes_as_text(): void
    {
        $this->fakeTelegram(forwardFails: true);
        $contact = $this->contact(['topic_id' => 70]);
        $message = $this->message($contact, 11, ['created_at' => now()->subMinutes(30), 'forward_failures' => 2, 'forward_alerted_at' => now()]);

        $this->artisan('telegram:resend-unforwarded')->assertSuccessful();

        $this->assertNotNull($message->fresh()->forwarded_at);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/sendMessage') && $r['message_thread_id'] === 70
            && str_contains($r['text'], 'вопрос про курс'));
    }

    private function fakeTelegram(bool $forwardFails = false): void
    {
        Http::fake(function (Request $r) use ($forwardFails) {
            if ($forwardFails && str_ends_with($r->url(), '/forwardMessage')) {
                return Http::response(['ok' => false, 'error_code' => 400, 'description' => 'Bad Request: message to forward not found'], 400);
            }
            if (str_ends_with($r->url(), '/createForumTopic')) {
                return Http::response(['ok' => true, 'result' => ['message_thread_id' => 500]]);
            }

            return Http::response(['ok' => true, 'result' => ['message_id' => 1]]);
        });
    }

    private function contact(array $attrs = []): TelegramContact
    {
        return TelegramContact::create($attrs + [
            'bot' => TelegramBots::LESSON,
            'telegram_user_id' => 555,
            'first_name' => 'Тест',
            'lesson' => 'cho',
            'link_token' => Str::random(16),
            'started_at' => now(),
            'last_start_at' => now(),
        ]);
    }

    private function message(TelegramContact $contact, int $telegramId, array $attrs = []): TelegramMessage
    {
        $message = TelegramMessage::create([
            'telegram_contact_id' => $contact->id,
            'direction' => TelegramMessage::DIRECTION_IN,
            'kind' => TelegramMessage::KIND_USER,
            'text' => 'вопрос про курс',
            'telegram_message_id' => $telegramId,
        ]);
        $message->forceFill($attrs)->save();

        return $message;
    }
}
