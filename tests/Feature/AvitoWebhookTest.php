<?php

namespace Tests\Feature;

use App\Models\AvitoMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AvitoWebhookTest extends TestCase
{
    private const OWN_ID = 1001;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.avito.client_id' => 'cid',
            'services.avito.client_secret' => 'csecret',
            'services.avito.webhook_secret' => 'hook-secret',
            'services.telegram_funnel.bot_token' => 'test-token',
            'services.telegram_funnel.admin_chat_id' => '-1001234567890',
        ]);

        Http::fake([
            'api.avito.ru/token' => Http::response(['access_token' => 'avito-token', 'expires_in' => 86400]),
            'api.avito.ru/messenger/v2/accounts/*/chats/*' => Http::response([
                'id' => 'u2i-abc',
                'created' => 1_800_000_000,
                'context' => ['type' => 'item', 'value' => ['id' => 55, 'title' => 'Репетитор по обществознанию', 'url' => 'https://www.avito.ru/moskva/predlozheniya_uslug/55', 'price_string' => '2 000 ₽']],
                'users' => [['id' => self::OWN_ID, 'name' => 'Александр'], ['id' => 2002, 'name' => 'Мария']],
            ]),
            '*/createForumTopic' => Http::response(['ok' => true, 'result' => ['message_thread_id' => 88]]),
            '*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
        ]);
    }

    private function hook(array $value, string $secret = 'hook-secret')
    {
        return $this->postJson("/api/avito/webhook/{$secret}", [
            'id' => 'evt-1',
            'version' => 'v3.0.0',
            'timestamp' => 1_800_000_000,
            'payload' => ['type' => 'message', 'value' => $value + [
                'chat_id' => 'u2i-abc',
                'user_id' => self::OWN_ID,
                'author_id' => 2002,
                'created' => 1_800_000_000,
                'type' => 'text',
                'chat_type' => 'u2i',
                'item_id' => 55,
            ]],
        ]);
    }

    public function test_first_message_goes_to_avito_topic_as_new_chat(): void
    {
        $this->hook(['id' => 'm1', 'content' => ['text' => 'Здравствуйте, <есть> места?']])->assertOk();

        $message = AvitoMessage::sole();
        $this->assertNotNull($message->sent_at);
        $this->assertSame(88, (int) DB::table('telegram_service_topics')->where('key', 'avito')->value('topic_id'));

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/createForumTopic') && $r['name'] === '🛒 Авито');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/sendMessage')
            && $r['message_thread_id'] === 88
            && $r['disable_notification'] === false
            && str_contains($r['text'], 'новый чат')
            && str_contains($r['text'], 'Мария')
            && str_contains($r['text'], 'Репетитор по обществознанию')
            && str_contains($r['text'], '2 000 ₽')
            && str_contains($r['text'], 'Здравствуйте, &lt;есть&gt; места?')
            && str_contains($r['text'], 'messenger/channel/u2i-abc'));
    }

    public function test_next_message_in_same_chat_is_not_new_chat(): void
    {
        $this->hook(['id' => 'm1', 'content' => ['text' => 'Привет']]);
        $this->hook(['id' => 'm2', 'content' => ['text' => 'Ещё вопрос'], 'created' => 1_800_000_100]);

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/sendMessage')
            && str_contains($r['text'], 'Ещё вопрос') && ! str_contains($r['text'], 'новый чат'));
    }

    public function test_repeated_webhook_is_sent_once(): void
    {
        $this->hook(['id' => 'm1', 'content' => ['text' => 'Привет']]);
        $this->hook(['id' => 'm1', 'content' => ['text' => 'Привет']]);

        $this->assertSame(1, AvitoMessage::count());
        Http::assertSentCount(4); // token, chat, createForumTopic, sendMessage
    }

    public function test_own_messages_are_skipped(): void
    {
        $this->hook(['id' => 'm1', 'author_id' => self::OWN_ID, 'content' => ['text' => 'Мой ответ']])->assertOk();

        $this->assertSame(0, AvitoMessage::count());
        Http::assertNothingSent();
    }

    public function test_wrong_secret_is_rejected(): void
    {
        $this->hook(['id' => 'm1', 'content' => ['text' => 'Привет']], 'wrong')->assertForbidden();

        config(['services.avito.webhook_secret' => '']);
        $this->hook(['id' => 'm1', 'content' => ['text' => 'Привет']], 'x')->assertForbidden();

        $this->assertSame(0, AvitoMessage::count());
    }

    public function test_message_is_sent_even_if_avito_chat_lookup_fails(): void
    {
        Http::fake([
            'api.avito.ru/*' => Http::response(['error' => 'boom'], 500),
            '*/createForumTopic' => Http::response(['ok' => true, 'result' => ['message_thread_id' => 88]]),
            '*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
        ]);

        $this->hook(['id' => 'm1', 'type' => 'image', 'content' => ['image' => ['sizes' => []]]])->assertOk();

        $this->assertNotNull(AvitoMessage::sole()->sent_at);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/sendMessage') && str_contains($r['text'], '[фото]'));
    }
}
