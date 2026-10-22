<?php

namespace Tests\Feature;

use App\Models\InboundLead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InboundLeadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'inbound_leads.sites' => [
                'landing' => ['topic' => '🌐 Заявки — landing.ru', 'key' => 'secret-landing'],
                'unset' => ['topic' => '🌐 Без ключа', 'key' => null],
            ],
            'services.telegram_funnel.bot_token' => 'test-token',
            'services.telegram_funnel.admin_chat_id' => '-1001234567890',
        ]);

        Http::fake([
            '*/createForumTopic' => Http::response(['ok' => true, 'result' => ['message_thread_id' => 77]]),
            '*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
        ]);
    }

    public function test_lead_goes_to_site_topic_with_sound(): void
    {
        $this->postJson('/api/inbound-lead', [
            'name' => 'Иван',
            'method' => 'Telegram',
            'contact' => '@ivanov',
            'form' => 'Попап — тарифы',
        ], ['X-Lead-Key' => 'secret-landing'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $lead = InboundLead::sole();
        $this->assertSame('landing', $lead->site);
        $this->assertSame('Попап — тарифы', $lead->form);
        $this->assertNotNull($lead->sent_at);
        $this->assertSame(77, (int) DB::table('telegram_service_topics')->where('key', 'site_landing')->value('topic_id'));

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/createForumTopic') && $r['name'] === '🌐 Заявки — landing.ru');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/sendMessage')
            && $r['message_thread_id'] === 77
            && $r['disable_notification'] === false
            && str_contains($r['text'], 'Иван')
            && str_contains($r['text'], 'Форма: Попап — тарифы')
            && str_contains($r['text'], 'href="https://t.me/ivanov"'));
    }

    public function test_plain_form_post_with_key_field_returns_json(): void
    {
        $this->post('/api/inbound-lead', ['key' => 'secret-landing', 'name' => 'Мария', 'contact' => '8 999 123-45-67'])
            ->assertOk();

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/sendMessage') && str_contains($r['text'], '+79991234567'));
    }

    public function test_unknown_or_empty_key_is_rejected(): void
    {
        $this->postJson('/api/inbound-lead', ['key' => 'wrong', 'name' => 'Иван', 'contact' => '@ivanov'])->assertStatus(401);
        $this->postJson('/api/inbound-lead', ['key' => '', 'name' => 'Иван', 'contact' => '@ivanov'])->assertStatus(401);

        $this->assertSame(0, InboundLead::count());
        Http::assertNothingSent();
    }

    public function test_validation_errors_are_json_even_without_accept_header(): void
    {
        $this->post('/api/inbound-lead', ['key' => 'secret-landing', 'name' => 'Иван'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('contact');
    }

    public function test_honeypot_pretends_success(): void
    {
        $this->postJson('/api/inbound-lead', ['key' => 'secret-landing', 'name' => 'Bot', 'contact' => '@bot', 'website' => 'http://spam'])
            ->assertOk();

        $this->assertSame(0, InboundLead::count());
        Http::assertNothingSent();
    }
}
