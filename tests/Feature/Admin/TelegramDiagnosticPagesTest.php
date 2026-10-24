<?php

namespace Tests\Feature\Admin;

use App\Models\DiagnosticAnswer;
use App\Models\DiagnosticAttempt;
use App\Models\TelegramContact;
use App\Models\User;
use App\Service\Telegram\TelegramBots;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Страницы админки с контактами бота диагностики — у них нет урока (lesson пуст). */
class TelegramDiagnosticPagesTest extends TestCase
{
    use RefreshDatabase;

    private TelegramContact $diag;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->diag = TelegramContact::create([
            'bot' => TelegramBots::DIAG, 'telegram_user_id' => 555, 'first_name' => 'Аня', 'source' => 'vk_diag',
            'link_token' => Str::random(16), 'started_at' => now(), 'last_start_at' => now(),
        ]);
        $attempt = DiagnosticAttempt::create([
            'token' => Str::random(32), 'telegram_contact_id' => $this->diag->id, 'status' => 'finished', 'grade' => '11',
            'target_score' => 85, 'block_order' => array_keys(config('diagnostic.blocks')), 'block_index' => 5,
            'levels' => ['society' => 3, 'economy' => 1, 'social' => 2, 'politics' => 2, 'law' => 0],
            'levels_low' => ['society' => 3, 'economy' => 1, 'social' => 2, 'politics' => 2, 'law' => 0],
            'result' => ['low' => 52, 'high' => 58, 'potential' => 74, 'gains' => ['law' => 9, 'economy' => 6, 'social' => 3, 'politics' => 3, 'society' => 0], 'weak' => ['law', 'economy'], 'guessed' => 2],
            'improve' => 'yes', 'improve_source' => 'page', 'improve_at' => now(),
            'started_at' => now()->subMinutes(10), 'finished_at' => now(),
        ]);
        DiagnosticAnswer::create([
            'diagnostic_attempt_id' => $attempt->id, 'block' => 'law', 'level' => 1, 'position' => 1, 'question_id' => 'law-1-1',
            'question_hash' => 'abc', 'mechanic' => 'swipe', 'answer' => ['value' => true], 'correct' => false, 'guess' => true,
            'answer_ms' => 4000, 'created_at' => now(),
        ]);
        TelegramContact::create([
            'bot' => TelegramBots::LESSON, 'telegram_user_id' => 777, 'first_name' => 'Петя', 'lesson' => 'cho',
            'link_token' => Str::random(16), 'started_at' => now(), 'last_start_at' => now(),
        ]);
    }

    public function test_diagnostic_stats_page(): void
    {
        $this->get(route('admin.telegram.diagnostic'))->assertOk()
            ->assertSee('vk_diag')
            ->assertSee('~52–58')
            ->assertSee('law-1-1')
            ->assertSee('TELEGRAM_DIAG_BOT_TOKEN');
    }

    public function test_contacts_list_is_per_bot(): void
    {
        $this->get(route('admin.telegram.contacts.index'))->assertOk()->assertSee('Петя')->assertDontSee('Аня');
        $this->get(route('admin.telegram.contacts.index', ['bot' => 'diag']))->assertOk()
            ->assertSee('Аня')->assertDontSee('Петя')->assertSee('Сейчас ~52–58', false);
    }

    public function test_diag_contact_card_and_timeline(): void
    {
        $this->get(route('admin.telegram.contacts.show', $this->diag))->assertOk()
            ->assertSee('бот диагностики')
            ->assertSee('Прошёл диагностику')
            ->assertSee('«Хочешь улучшить свои знания?» — да');
    }

    public function test_texts_page_has_both_bots(): void
    {
        $this->get(route('admin.telegram.texts.edit'))->assertOk()
            ->assertSee('Бот пробного урока')->assertSee('Бот диагностики')->assertSee('texts[diag_question]', false);

        $this->put(route('admin.telegram.texts.update'), ['texts' => ['diag_question' => 'Привет! Что сложнее всего?']])->assertRedirect();
        $this->assertSame('Привет! Что сложнее всего?', \App\Service\Telegram\FunnelTexts::all()['diag_question']);
    }
}
