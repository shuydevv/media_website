<?php

namespace Tests\Feature;

use App\Models\AvitoAccount;
use App\Models\AvitoChat;
use App\Models\AvitoMessage;
use App\Models\User;
use App\Service\Telegram\FunnelBot;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AvitoWebhookTest extends TestCase
{
    private const OWN_ID = 1001;

    /** Тема чата и лента «🛒 Авито» — createForumTopic отдаёт их по очереди. */
    private const CHAT_TOPIC = 77;

    private const FEED_TOPIC = 88;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.avito.webhook_secret' => 'hook-secret',
            'services.telegram_funnel.bot_token' => 'test-token',
            'services.telegram_funnel.admin_chat_id' => '-1001234567890',
        ]);

        AvitoAccount::create(['name' => 'Репетитор ЕГЭ', 'client_id' => 'cid', 'client_secret' => 'csecret', 'avito_user_id' => self::OWN_ID]);

        $this->fakeHttp();
    }

    /** $extra — заглушки, которые должны победить стандартные (первая подходящая побеждает). */
    private function fakeHttp(array $extra = []): void
    {
        Http::swap(new HttpFactory());
        Http::fake($extra + [
            'api.avito.ru/token' => Http::response(['access_token' => 'avito-token', 'expires_in' => 86400]),
            'api.avito.ru/messenger/v2/accounts/*/chats/*' => Http::response([
                'id' => 'u2i-abc',
                'created' => 1_800_000_000,
                'context' => ['type' => 'item', 'value' => ['id' => 55, 'title' => 'Репетитор по обществознанию', 'url' => 'https://www.avito.ru/moskva/predlozheniya_uslug/55', 'price_string' => '2 000 ₽']],
                'users' => [['id' => self::OWN_ID, 'name' => 'Александр'], ['id' => 2002, 'name' => 'Мария']],
            ]),
            'api.avito.ru/*/messages' => Http::response(['id' => 'out-1', 'direction' => 'out', 'type' => 'text']),
            'api.avito.ru/*/read' => Http::response(['ok' => true]),
            '*/createForumTopic' => Http::sequence()
                ->push(['ok' => true, 'result' => ['message_thread_id' => self::CHAT_TOPIC]])
                ->push(['ok' => true, 'result' => ['message_thread_id' => self::FEED_TOPIC]])
                ->whenEmpty(Http::response(['ok' => true, 'result' => ['message_thread_id' => 99]])),
            '*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
            '*/setMessageReaction' => Http::response(['ok' => true, 'result' => true]),
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

    /** Сообщение админа в теме $thread админ-группы. */
    private function adminMessage(int $thread, array $extra = []): void
    {
        FunnelBot::make()->handleUpdate(['update_id' => 1, 'message' => $extra + [
            'message_id' => 500,
            'chat' => ['id' => -1001234567890, 'type' => 'supergroup'],
            'from' => ['id' => 7, 'is_bot' => false, 'first_name' => 'Админ'],
            'message_thread_id' => $thread,
            'is_topic_message' => true,
            'text' => 'Да, есть места',
            'reply_to_message' => ['message_id' => $thread, 'forum_topic_created' => ['name' => 'x']],
        ]]);
    }

    private function sentToTopic(int $topic, ?callable $check = null): bool
    {
        return Http::recorded(fn ($r) => str_ends_with($r->url(), '/sendMessage')
            && ($r['message_thread_id'] ?? null) === $topic && (! $check || $check($r)))->isNotEmpty();
    }

    public function test_first_message_opens_chat_topic_with_card(): void
    {
        $this->hook(['id' => 'm1', 'content' => ['text' => 'Здравствуйте, <есть> места?']])->assertOk();

        $chat = AvitoChat::sole();
        $this->assertSame(self::CHAT_TOPIC, $chat->topic_id);
        $this->assertSame('Мария', $chat->client_name);
        $this->assertNotNull(AvitoMessage::sole()->sent_at);

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/createForumTopic') && $r['name'] === '🛒 Мария · Репетитор ЕГЭ');
        $this->assertTrue($this->sentToTopic(self::CHAT_TOPIC, fn ($r) => $r['disable_notification'] === true
            && str_contains($r['text'], 'Авито · Репетитор ЕГЭ')
            && str_contains($r['text'], 'Репетитор по обществознанию')
            && str_contains($r['text'], '2 000 ₽')
            && str_contains($r['text'], 'messenger/channel/u2i-abc')));
        $this->assertTrue($this->sentToTopic(self::CHAT_TOPIC, fn ($r) => $r['disable_notification'] === false
            && $r['text'] === 'Здравствуйте, &lt;есть&gt; места?'));
        $this->assertTrue($this->sentToTopic(self::FEED_TOPIC, fn ($r) => $r['disable_notification'] === true
            && str_contains($r['text'], 'Мария') && str_contains($r['text'], 't.me/c/1234567890/'.self::CHAT_TOPIC)));
    }

    public function test_next_message_goes_to_same_topic(): void
    {
        $this->hook(['id' => 'm1', 'content' => ['text' => 'Привет']]);
        $this->fakeHttp();
        $this->hook(['id' => 'm2', 'content' => ['text' => 'Ещё вопрос'], 'created' => 1_800_000_100]);

        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/createForumTopic'));
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/messenger/v2/'));
        $this->assertTrue($this->sentToTopic(self::CHAT_TOPIC, fn ($r) => $r['text'] === 'Ещё вопрос'));
    }

    public function test_deleted_topic_is_recreated(): void
    {
        AvitoChat::create(['chat_id' => 'u2i-abc', 'user_id' => self::OWN_ID, 'client_name' => 'Мария', 'topic_id' => 50]);
        $this->fakeHttp([
            '*/sendMessage' => function ($r) {
                return ($r['message_thread_id'] ?? null) === 50
                    ? Http::response(['ok' => false, 'error_code' => 400, 'description' => 'Bad Request: message thread not found'])
                    : Http::response(['ok' => true, 'result' => ['message_id' => 1]]);
            },
        ]);

        $this->hook(['id' => 'm1', 'content' => ['text' => 'Привет']]);

        $this->assertSame(self::CHAT_TOPIC, AvitoChat::sole()->topic_id);
        $this->assertTrue($this->sentToTopic(self::CHAT_TOPIC, fn ($r) => $r['text'] === 'Привет'));
    }

    public function test_repeated_webhook_is_sent_once(): void
    {
        $this->hook(['id' => 'm1', 'content' => ['text' => 'Привет']]);
        $this->hook(['id' => 'm1', 'content' => ['text' => 'Привет']]);

        $this->assertSame(1, AvitoMessage::count());
        $this->assertSame(1, Http::recorded(fn ($r) => str_ends_with($r->url(), '/sendMessage') && ($r['text'] ?? '') === 'Привет')->count());
    }

    public function test_own_reply_from_avito_app_is_shown_silently(): void
    {
        $this->hook(['id' => 'm1', 'content' => ['text' => 'Есть места?']]);
        $this->hook(['id' => 'm2', 'author_id' => self::OWN_ID, 'content' => ['text' => 'Есть, в среду']])->assertOk();

        $this->assertSame(AvitoMessage::OUT, AvitoMessage::where('message_id', 'm2')->value('direction'));
        $this->assertTrue($this->sentToTopic(self::CHAT_TOPIC, fn ($r) => $r['disable_notification'] === true
            && str_contains($r['text'], 'ответ из Авито') && str_contains($r['text'], 'Есть, в среду')));
    }

    public function test_message_in_chat_topic_goes_to_client(): void
    {
        $this->hook(['id' => 'm1', 'content' => ['text' => 'Есть места?']]);

        $this->adminMessage(self::CHAT_TOPIC);

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/messenger/v1/accounts/1001/chats/u2i-abc/messages')
            && $r['message'] === ['text' => 'Да, есть места'] && $r['type'] === 'text');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/chats/u2i-abc/read'));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/setMessageReaction') && $r['message_id'] === 500);

        $out = AvitoMessage::where('message_id', 'out-1')->sole();
        $this->assertTrue($out->from_bot);
    }

    public function test_echo_of_bot_reply_is_not_duplicated(): void
    {
        $this->hook(['id' => 'm1', 'content' => ['text' => 'Есть места?']]);
        $this->adminMessage(self::CHAT_TOPIC);

        $this->hook(['id' => 'out-1', 'author_id' => self::OWN_ID, 'content' => ['text' => 'Да, есть места']])->assertOk();

        $this->assertFalse($this->sentToTopic(self::CHAT_TOPIC, fn ($r) => str_contains($r['text'], 'ответ из Авито')));
    }

    public function test_notes_and_media_in_chat_topic_are_not_sent(): void
    {
        $this->hook(['id' => 'm1', 'content' => ['text' => 'Есть места?']]);

        $this->adminMessage(self::CHAT_TOPIC, ['text' => '// перезвонить завтра']);
        $this->adminMessage(self::CHAT_TOPIC, ['text' => null, 'photo' => [['file_id' => 'f']]]);

        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/messages') && str_contains($r->url(), 'avito'));
        $this->assertTrue($this->sentToTopic(self::CHAT_TOPIC, fn ($r) => str_contains($r['text'], 'только текст')));
    }

    public function test_legacy_reply_in_feed_still_works(): void
    {
        DB::table('telegram_service_topics')->insert(['key' => 'avito', 'topic_id' => self::FEED_TOPIC, 'created_at' => now(), 'updated_at' => now()]);
        AvitoMessage::create(['message_id' => 'old', 'chat_id' => 'u2i-old', 'user_id' => self::OWN_ID, 'author_id' => 2002,
            'type' => 'text', 'text' => 'Старое', 'telegram_message_id' => 42, 'sent_at' => now()]);

        $this->adminMessage(self::FEED_TOPIC, ['reply_to_message' => ['message_id' => 42, 'from' => ['id' => 1, 'is_bot' => true]]]);

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/accounts/1001/chats/u2i-old/messages'));
    }

    public function test_message_in_feed_without_reply_gets_hint(): void
    {
        DB::table('telegram_service_topics')->insert(['key' => 'avito', 'topic_id' => self::FEED_TOPIC, 'created_at' => now(), 'updated_at' => now()]);

        $this->adminMessage(self::FEED_TOPIC);

        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/messages') && str_contains($r->url(), 'avito'));
        $this->assertTrue($this->sentToTopic(self::FEED_TOPIC, fn ($r) => str_contains($r['text'], 'его теме')));
    }

    public function test_avito_error_is_reported_in_topic(): void
    {
        $this->hook(['id' => 'm1', 'content' => ['text' => 'Есть места?']]);
        $this->fakeHttp(['api.avito.ru/*/messages' => Http::response(['error' => 'forbidden'], 403)]);

        $this->adminMessage(self::CHAT_TOPIC);

        $this->assertTrue($this->sentToTopic(self::CHAT_TOPIC, fn ($r) => str_contains($r['text'], 'Не отправлено на Авито') && str_contains($r['text'], 'тариф')));
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/setMessageReaction'));
    }

    public function test_second_account_uses_its_own_keys_and_label(): void
    {
        AvitoAccount::create(['name' => 'Второй', 'client_id' => 'cid2', 'client_secret' => 'sec2', 'avito_user_id' => 3003]);

        $this->hook(['id' => 'm1', 'user_id' => 3003, 'content' => ['text' => 'Привет']]);
        $this->adminMessage(self::CHAT_TOPIC);

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/createForumTopic') && $r['name'] === '🛒 Мария · Второй');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/token') && $r['client_id'] === 'cid2');
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/token') && $r['client_id'] === 'cid');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/accounts/3003/chats/u2i-abc/messages'));
    }

    public function test_account_without_profile_id_resolves_it_on_first_message(): void
    {
        AvitoAccount::query()->update(['avito_user_id' => null]);
        $this->fakeHttp(['api.avito.ru/core/v1/accounts/self' => Http::response(['id' => self::OWN_ID, 'name' => 'Полтавский'])]);

        $this->hook(['id' => 'm1', 'content' => ['text' => 'Привет']]);

        $this->assertSame(self::OWN_ID, AvitoAccount::sole()->avito_user_id);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/createForumTopic') && str_contains($r['name'], 'Репетитор ЕГЭ'));
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
        $this->fakeHttp(['api.avito.ru/*' => Http::response(['error' => 'boom'], 500)]);

        $this->hook(['id' => 'm1', 'type' => 'image', 'content' => ['image' => ['sizes' => []]]])->assertOk();

        $this->assertNotNull(AvitoMessage::sole()->sent_at);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/createForumTopic') && $r['name'] === '🛒 Клиент Авито · Репетитор ЕГЭ');
        $this->assertTrue($this->sentToTopic(self::CHAT_TOPIC, fn ($r) => $r['text'] === '[фото]'));
    }

    public function test_admin_adds_account_and_it_gets_subscribed(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->fakeHttp([
            'api.avito.ru/core/v1/accounts/self' => Http::response(['id' => 4004, 'name' => 'Новый профиль']),
            'api.avito.ru/messenger/v3/webhook' => Http::response(['ok' => true]),
        ]);

        $this->actingAs($admin)->post(route('admin.telegram.avito.store'), [
            'name' => 'Новый', 'client_id' => 'cid4', 'client_secret' => 'sec4',
        ])->assertRedirect(route('admin.telegram.avito.index'))->assertSessionHas('success');

        $account = AvitoAccount::where('client_id', 'cid4')->sole();
        $this->assertSame(4004, $account->avito_user_id);
        $this->assertNotNull($account->subscribed_at);
        $this->assertSame('sec4', $account->client_secret);
        $this->assertNotSame('sec4', DB::table('avito_accounts')->where('id', $account->id)->value('client_secret'));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/messenger/v3/webhook') && str_ends_with($r['url'], '/api/avito/webhook/hook-secret'));

        $this->actingAs($admin)->get(route('admin.telegram.avito.index'))
            ->assertOk()->assertSee('Новый профиль')->assertSee('Подключён')->assertDontSee('sec4');
    }

    public function test_admin_sees_connect_error(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->fakeHttp(['api.avito.ru/token' => Http::response(['error' => 'invalid_client'], 400)]);

        $this->actingAs($admin)->post(route('admin.telegram.avito.store'), [
            'name' => 'Плохой', 'client_id' => 'bad', 'client_secret' => 'bad',
        ])->assertSessionHas('error');

        $this->assertNotNull(AvitoAccount::where('client_id', 'bad')->value('last_error'));
    }

    public function test_daily_check_passes_for_healthy_account(): void
    {
        $this->fakeHttp([
            'api.avito.ru/core/v1/accounts/self' => Http::response(['id' => self::OWN_ID, 'name' => 'Полтавский']),
            'api.avito.ru/messenger/v1/subscriptions' => Http::response(['subscriptions' => [['url' => route('avito.webhook', ['secret' => 'hook-secret']), 'version' => '3']]]),
        ]);

        $this->artisan('avito:check')->assertSuccessful();

        $this->assertNull(AvitoAccount::sole()->last_error);
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/sendMessage'));
    }

    public function test_daily_check_resubscribes_lost_subscription(): void
    {
        $this->fakeHttp([
            'api.avito.ru/core/v1/accounts/self' => Http::response(['id' => self::OWN_ID, 'name' => 'Полтавский']),
            'api.avito.ru/messenger/v1/subscriptions' => Http::response(['subscriptions' => []]),
            'api.avito.ru/messenger/v3/webhook' => Http::response(['ok' => true]),
        ]);

        $this->artisan('avito:check')->assertSuccessful();

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/messenger/v3/webhook'));
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/sendMessage'));
    }

    public function test_daily_check_alerts_on_broken_account(): void
    {
        $this->fakeHttp(['api.avito.ru/token' => Http::response(['error' => 'invalid_client'], 400)]);

        $this->artisan('avito:check')->assertFailed();

        $this->assertNotNull(AvitoAccount::sole()->last_error);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/createForumTopic') && $r['name'] === '⚠️ Сбои');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/sendMessage') && $r['disable_notification'] === false
            && str_contains($r['text'], 'Репетитор ЕГЭ') && str_contains($r['text'], 'тариф'));
    }
}
