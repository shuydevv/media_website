<?php

namespace Tests\Feature\Telegram;

use App\Models\TelegramBroadcast;
use App\Models\TelegramBroadcastRecipient;
use App\Models\TelegramContact;
use App\Models\TelegramMessage;
use App\Models\User;
use App\Service\Telegram\Broadcast\BroadcastAudience;
use App\Service\Telegram\Broadcast\BroadcastDesk;
use App\Service\Telegram\Broadcast\BroadcastSender;
use App\Service\Telegram\DiagBot;
use App\Service\Telegram\FunnelBot;
use App\Service\Telegram\TelegramBots;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Рассылки из темы «📣 Рассылка» (Broadcast\BroadcastDesk / BroadcastSender).
 * Очередь в тестах синхронная: «Да, отправить» сразу проходит всю отправку,
 * Http::fake записывает запросы к Telegram.
 */
class BroadcastTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_CHAT = -1001234567890;

    private const TOPIC = 77;

    private const CARD = 9001;

    /** Ответ Telegram на copyMessages; null — успех. */
    private ?\Closure $copyResponse = null;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram_funnel.bot_token' => 'lesson-token',
            'services.telegram_funnel.admin_chat_id' => (string) self::ADMIN_CHAT,
            'services.telegram_diag.bot_token' => 'diag-token',
        ]);
        DB::table('telegram_service_topics')->insert(['key' => 'broadcast', 'topic_id' => self::TOPIC, 'created_at' => now(), 'updated_at' => now()]);

        $this->fakeTelegram();
    }

    public function test_message_in_topic_becomes_draft_with_card_and_sends_nothing(): void
    {
        $this->contact(TelegramBots::LESSON, 501);
        $this->contact(TelegramBots::DIAG, 502);

        FunnelBot::make()->handleUpdate($this->topicMessage(10, 'Завтра вебинар в 19:00'));

        $broadcast = TelegramBroadcast::sole();
        $this->assertSame(TelegramBroadcast::STATUS_DRAFT, $broadcast->status);
        $this->assertSame([10], $broadcast->source_message_ids);
        $this->assertSame(self::CARD, (int) $broadcast->card_message_id);

        $card = Http::recorded(fn (Request $r) => $this->to($r, 'lesson', 'sendMessage'))->first()[0];
        $this->assertSame(self::TOPIC, $card['message_thread_id']);
        $this->assertStringContainsString('Получат: <b>2</b>', $card['text']);
        $this->assertSame(0, $this->countSent('copyMessages'), 'без подтверждения ничего не уходит');
    }

    public function test_notes_commands_and_other_topics_are_not_drafts(): void
    {
        FunnelBot::make()->handleUpdate($this->topicMessage(10, '// это заметка'));
        FunnelBot::make()->handleUpdate($this->topicMessage(11, '/emoji'));
        FunnelBot::make()->handleUpdate($this->topicMessage(12, 'в чужой теме', 55));

        $this->assertSame(0, TelegramBroadcast::count());
    }

    public function test_album_parts_join_one_draft(): void
    {
        foreach ([[21, 'Подпись'], [20, null]] as [$id, $caption]) {
            $m = $this->topicMessage($id, null);
            $m['message']['photo'] = [['file_id' => 'p'.$id]];
            $m['message']['media_group_id'] = 'album-1';
            if ($caption) {
                $m['message']['caption'] = $caption;
            }
            FunnelBot::make()->handleUpdate($m);
        }

        $broadcast = TelegramBroadcast::sole();
        $this->assertSame([20, 21], $broadcast->source_message_ids, 'по возрастанию — так требует copyMessages');
        $this->assertSame(2, $broadcast->media_count);
        $this->assertSame('Подпись', $broadcast->text);
    }

    public function test_audience_one_message_per_person_and_exclusions(): void
    {
        // В обоих ботах; последним пользовался ботом диагностики.
        $this->contact(TelegramBots::LESSON, 601, ['started_at' => now()->subDays(5), 'last_start_at' => now()->subDays(5)]);
        $this->contact(TelegramBots::DIAG, 601, ['started_at' => now()->subDay(), 'last_start_at' => now()->subDay()]);
        // В обоих, но диагностику заблокировал — получит от урока.
        $this->contact(TelegramBots::LESSON, 602, ['started_at' => now()->subDays(5)]);
        $this->contact(TelegramBots::DIAG, 602, ['started_at' => now()->subDay(), 'blocked_at' => now()]);
        $this->contact(TelegramBots::LESSON, 603, ['blocked_at' => now()]);
        $this->contact(TelegramBots::LESSON, 604, ['broadcast_opt_out_at' => now()]);
        $this->contact(TelegramBots::LESSON, 605);

        $broadcast = $this->draft(['excluded_user_ids' => [605]]);
        $a = BroadcastAudience::for($broadcast);

        $this->assertEqualsCanonicalizing(
            [[601, 'diag'], [602, 'lesson']],
            $a->recipients->map(fn ($c) => [(int) $c->telegram_user_id, $c->bot])->all(),
        );
        $this->assertSame(2, $a->duplicates);
        $this->assertEquals([
            BroadcastAudience::REASON_BLOCKED => 1,
            BroadcastAudience::REASON_OPT_OUT => 1,
            BroadcastAudience::REASON_HERE => 1,
        ], $a->excludedByReason());

        $broadcast->update(['audience' => TelegramBots::LESSON]);
        $this->assertEqualsCanonicalizing([601, 602], BroadcastAudience::for($broadcast)->recipients->pluck('telegram_user_id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_send_needs_confirmation_then_copies_through_contacts_bot(): void
    {
        $lesson = $this->contact(TelegramBots::LESSON, 701, ['reminder_due_at' => now()->addMinutes(10)]);
        $diag = $this->contact(TelegramBots::DIAG, 702);
        $broadcast = $this->draft();

        FunnelBot::make()->handleUpdate($this->press($broadcast, 'go'));
        $this->assertSame(TelegramBroadcast::STATUS_DRAFT, $broadcast->fresh()->status, '«Да» без «Отправить» не срабатывает');

        FunnelBot::make()->handleUpdate($this->press($broadcast, 'send'));
        $this->assertSame(BroadcastDesk::STATE_CONFIRM, $broadcast->fresh()->card_state);
        $this->assertSame(0, $this->countSent('copyMessages'));

        FunnelBot::make()->handleUpdate($this->press($broadcast, 'go'));

        $broadcast->refresh();
        $this->assertSame(TelegramBroadcast::STATUS_DONE, $broadcast->status);
        $this->assertSame(2, $broadcast->recipients()->where('status', TelegramBroadcastRecipient::STATUS_SENT)->count());

        $copies = Http::recorded(fn (Request $r) => str_ends_with($r->url(), '/copyMessages'));
        $this->assertCount(2, $copies);
        $byBot = $copies->mapWithKeys(fn ($pair) => [(int) $pair[0]['chat_id'] => str_contains($pair[0]->url(), 'diag-token') ? 'diag' : 'lesson']);
        $this->assertSame('lesson', $byBot[701]);
        $this->assertSame('diag', $byBot[702]);
        $this->assertSame([10], $copies->first()[0]['message_ids']);
        $this->assertSame(self::ADMIN_CHAT, (int) $copies->first()[0]['from_chat_id']);

        $lesson->refresh();
        $this->assertNull($lesson->first_admin_message_at, 'рассылка — не живой ответ: автоматика воронки продолжается');
        $this->assertNotNull($lesson->last_broadcast_at);
        $this->assertTrue($lesson->reminder_due_at->gte(now()->addMinutes(BroadcastSender::QUIET_MINUTES - 1)), 'близкое автосообщение сдвинуто');
        $this->assertSame(1, TelegramMessage::where('telegram_contact_id', $diag->id)->where('kind', TelegramMessage::KIND_BROADCAST)->count());

        $this->assertSame(1, $this->countSent('sendMessage', fn (Request $r) => str_contains((string) $r['text'], 'отправлена')), 'итог — отдельным сообщением');
    }

    public function test_blocked_user_is_marked_and_others_still_get_it(): void
    {
        $this->copyResponse = fn (Request $r) => (int) $r['chat_id'] === 801
            ? Http::response(['ok' => false, 'error_code' => 403, 'description' => 'Forbidden: bot was blocked by the user'], 403)
            : null;
        $blocked = $this->contact(TelegramBots::LESSON, 801);
        $this->contact(TelegramBots::LESSON, 802);
        $broadcast = $this->draft();

        BroadcastSender::make()->start($broadcast);

        $this->assertNotNull($blocked->fresh()->blocked_at);
        $this->assertSame([TelegramBroadcastRecipient::STATUS_BLOCKED, TelegramBroadcastRecipient::STATUS_SENT],
            $broadcast->recipients()->orderBy('telegram_contact_id')->pluck('status')->all());
        $this->assertSame(TelegramBroadcast::STATUS_DONE, $broadcast->fresh()->status);
    }

    public function test_deleted_source_stops_broadcast(): void
    {
        $this->copyResponse = fn () => Http::response(['ok' => false, 'error_code' => 400, 'description' => 'Bad Request: message to copy not found'], 400);
        $this->contact(TelegramBots::LESSON, 811);
        $this->contact(TelegramBots::LESSON, 812);
        $broadcast = $this->draft();

        BroadcastSender::make()->start($broadcast);

        $broadcast->refresh();
        $this->assertSame(TelegramBroadcast::STATUS_STOPPED, $broadcast->status);
        $this->assertStringContainsString('удалено', $broadcast->stop_reason);
        $this->assertSame(1, $this->countSent('copyMessages'), 'после первой ошибки остальным не пытаемся');
    }

    public function test_claimed_recipient_is_not_sent_twice(): void
    {
        $contact = $this->contact(TelegramBots::LESSON, 821);
        $broadcast = $this->draft(['status' => TelegramBroadcast::STATUS_SENDING, 'started_at' => now(), 'progress_at' => now()]);
        $broadcast->recipients()->create(['telegram_contact_id' => $contact->id, 'status' => TelegramBroadcastRecipient::STATUS_SENDING]);

        BroadcastSender::make()->runChunk($broadcast);
        $this->assertSame(0, $this->countSent('copyMessages'));

        // Задача упала посреди отправки: дошло ли — неизвестно, не повторяем.
        $broadcast->forceFill(['progress_at' => now()->subMinutes(5)])->save();
        BroadcastSender::recoverStuck();
        $this->assertSame(TelegramBroadcastRecipient::STATUS_FAILED, $broadcast->recipients()->value('status'));
        $this->assertSame(0, $this->countSent('copyMessages'));
        $this->assertSame(TelegramBroadcast::STATUS_DONE, $broadcast->fresh()->status);
    }

    public function test_reply_to_card_excludes_and_returns_people(): void
    {
        $this->contact(TelegramBots::LESSON, 901, ['username' => 'ivan_p']);
        $this->contact(TelegramBots::DIAG, 902, ['topic_id' => 333]);
        $broadcast = $this->draft();

        FunnelBot::make()->handleUpdate($this->replyToCard(30, '@Ivan_P https://t.me/c/1234567890/333 @nobody_here'));
        $this->assertEqualsCanonicalizing([901, 902], $broadcast->fresh()->excludedUserIds());
        $this->assertSame(1, $this->countSent('sendMessage', fn (Request $r) => str_contains((string) $r['text'], 'Не нашёл') && str_contains((string) $r['text'], '@nobody_here')));

        FunnelBot::make()->handleUpdate($this->replyToCard(31, 'вернуть @ivan_p'));
        $this->assertSame([902], $broadcast->fresh()->excludedUserIds());
        $this->assertSame(0, TelegramBroadcast::where('id', '!=', $broadcast->id)->count(), 'ответ на карточку — не новый черновик');
    }

    public function test_reply_with_time_schedules_after_confirmation(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-28 15:00'));
        $broadcast = $this->draft();

        FunnelBot::make()->handleUpdate($this->replyToCard(30, 'завтра 10:00'));
        $broadcast->refresh();
        $this->assertSame(BroadcastDesk::STATE_PLAN, $broadcast->card_state);
        $this->assertSame(TelegramBroadcast::STATUS_DRAFT, $broadcast->status);

        FunnelBot::make()->handleUpdate($this->press($broadcast, 'plan'));
        $broadcast->refresh();
        $this->assertSame(TelegramBroadcast::STATUS_SCHEDULED, $broadcast->status);
        $this->assertSame('2026-10-29 10:00', $broadcast->scheduled_at->format('Y-m-d H:i'));

        $this->contact(TelegramBots::LESSON, 950);
        $this->artisan('telegram:broadcasts')->assertSuccessful();
        $this->assertSame(TelegramBroadcast::STATUS_SCHEDULED, $broadcast->fresh()->status, 'рано');

        $this->travelTo(CarbonImmutable::parse('2026-10-29 10:00:30'));
        $this->artisan('telegram:broadcasts')->assertSuccessful();
        $this->assertSame(TelegramBroadcast::STATUS_DONE, $broadcast->fresh()->status);
        $this->assertSame(1, $this->countSent('copyMessages'));
    }

    public function test_parse_time(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-28 15:00'));

        $this->assertSame('2026-10-28 18:30', BroadcastDesk::parseTime('18:30')->format('Y-m-d H:i'));
        $this->assertSame('2026-10-29 09:00', BroadcastDesk::parseTime('в 9:00')->format('Y-m-d H:i'), 'прошедшее сегодня — завтра');
        $this->assertSame('2026-10-29 10:00', BroadcastDesk::parseTime('Завтра 10:00')->format('Y-m-d H:i'));
        $this->assertSame('2026-11-02 12:15', BroadcastDesk::parseTime('02.11 12:15')->format('Y-m-d H:i'));
        $this->assertIsString(BroadcastDesk::parseTime('сегодня 9:00'), 'явно сегодня, но уже прошло');
        $this->assertIsString(BroadcastDesk::parseTime('31.02 10:00'));
        $this->assertIsString(BroadcastDesk::parseTime('25:00'));
        $this->assertNull(BroadcastDesk::parseTime('@ivan'));
    }

    public function test_opt_out_command_in_persons_topic(): void
    {
        $lesson = $this->contact(TelegramBots::LESSON, 1001, ['topic_id' => 444]);
        $diag = $this->contact(TelegramBots::DIAG, 1001);

        FunnelBot::make()->handleUpdate($this->topicMessage(40, '/norassylka', 444));
        $this->assertNotNull($lesson->fresh()->broadcast_opt_out_at);
        $this->assertNotNull($diag->fresh()->broadcast_opt_out_at, 'исключается человек, а не один бот');
        $this->assertSame(0, $this->countSent('copyMessage'), 'команда не уходит человеку');

        FunnelBot::make()->handleUpdate($this->topicMessage(41, '/rassylka', 444));
        $this->assertNull($lesson->fresh()->broadcast_opt_out_at);
    }

    public function test_opt_out_command_with_list_of_people(): void
    {
        $ivan = $this->contact(TelegramBots::LESSON, 1011, ['username' => 'ivan_p']);
        $maria = $this->contact(TelegramBots::DIAG, 1012, ['username' => 'Maria']);
        $byId = $this->contact(TelegramBots::LESSON, 5550001013);
        $other = $this->contact(TelegramBots::LESSON, 1014, ['username' => 'other']);

        // В общей ленте (без темы): ники с @ и без, id, неизвестный ник.
        $general = $this->topicMessage(50, '/norassylka @ivan_p maria 5550001013 @ghost');
        unset($general['message']['message_thread_id'], $general['message']['is_topic_message'], $general['message']['reply_to_message']);
        FunnelBot::make()->handleUpdate($general);

        $this->assertNotNull($ivan->fresh()->broadcast_opt_out_at);
        $this->assertNotNull($maria->fresh()->broadcast_opt_out_at);
        $this->assertNotNull($byId->fresh()->broadcast_opt_out_at);
        $this->assertNull($other->fresh()->broadcast_opt_out_at);
        $this->assertSame(1, $this->countSent('sendMessage', fn (Request $r) => str_contains((string) $r['text'], 'Исключены из всех рассылок')
            && str_contains((string) $r['text'], '@ghost') && ! isset($r['message_thread_id'])));

        // В теме «📣 Рассылка» — тоже команда, а не черновик.
        FunnelBot::make()->handleUpdate($this->topicMessage(51, '/rassylka ivan_p'));
        $this->assertNull($ivan->fresh()->broadcast_opt_out_at);
        $this->assertNotNull($maria->fresh()->broadcast_opt_out_at);
        $this->assertSame(0, TelegramBroadcast::count());
    }

    public function test_reply_after_broadcast_is_marked_in_topic(): void
    {
        $contact = $this->contact(TelegramBots::DIAG, 1101, ['topic_id' => 555, 'topic_status' => '🆕']);
        $broadcast = $this->draft();
        BroadcastSender::make()->start($broadcast);

        DiagBot::make()->handleUpdate(['update_id' => 1, 'message' => [
            'message_id' => 3, 'from' => ['id' => 1101, 'is_bot' => false, 'first_name' => 'Аня'],
            'chat' => ['id' => 1101, 'type' => 'private'], 'text' => 'А можно подробнее?',
        ]]);

        $this->assertNotNull($broadcast->recipients()->value('replied_at'));
        $this->assertStringEndsWith('📣', $contact->fresh()->topic_status);
        $this->assertSame(1, $this->countSent('sendMessage', fn (Request $r) => str_contains((string) $r['text'], 'Ответ на рассылку #'.$broadcast->id), 'diag'));
        $this->assertSame(1, $this->countSent('editForumTopic', fn (Request $r) => str_contains((string) $r['name'], '📣'), 'diag'));
    }

    public function test_admin_pages_and_bulk_exclusion(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->contact(TelegramBots::LESSON, 1201, ['first_name' => 'Борис']);
        $this->contact(TelegramBots::LESSON, 1202, ['first_name' => 'Вера']);
        $broadcast = $this->draft();

        $this->actingAs($admin)->get(route('admin.telegram.broadcasts.index'))->assertOk()->assertSee('#'.$broadcast->id);
        $this->actingAs($admin)->get(route('admin.telegram.broadcasts.show', $broadcast))->assertOk()->assertSee('Борис')->assertSee('Вера');

        $this->actingAs($admin)->post(route('admin.telegram.broadcasts.people', $broadcast), ['action' => 'here', 'ids' => [1201]])->assertRedirect();
        $this->assertSame([1201], $broadcast->fresh()->excludedUserIds());

        $this->actingAs($admin)->post(route('admin.telegram.broadcasts.people', $broadcast), ['action' => 'all', 'ids' => [1202]])->assertRedirect();
        $this->assertNotNull(TelegramContact::where('telegram_user_id', 1202)->value('broadcast_opt_out_at'));

        $this->actingAs($admin)->get(route('admin.telegram.broadcasts.show', ['broadcast' => $broadcast, 'tab' => 'out']))
            ->assertOk()->assertSee('исключён из этой рассылки')->assertSee('исключён из всех рассылок');

        BroadcastSender::make()->start($broadcast->fresh());
        $this->actingAs($admin)->get(route('admin.telegram.broadcasts.show', $broadcast))->assertOk();
        $this->actingAs($admin)->post(route('admin.telegram.broadcasts.people', $broadcast), ['action' => 'here', 'ids' => [1201]])
            ->assertSessionHas('error');
    }

    // ---------- помощники ----------

    private function fakeTelegram(): void
    {
        Http::fake(function (Request $r) {
            if (str_ends_with($r->url(), '/copyMessages')) {
                return ($this->copyResponse ? ($this->copyResponse)($r) : null)
                    ?? Http::response(['ok' => true, 'result' => [['message_id' => 5]]]);
            }

            return Http::response(['ok' => true, 'result' => ['message_id' => self::CARD, 'message_thread_id' => 600]]);
        });
    }

    private function contact(string $bot, int $userId, array $extra = []): TelegramContact
    {
        return TelegramContact::create($extra + [
            'bot' => $bot, 'telegram_user_id' => $userId, 'lesson' => $bot === TelegramBots::LESSON ? 'cho' : null,
            'link_token' => Str::random(16), 'started_at' => now()->subDays(2), 'last_start_at' => now()->subDays(2),
        ]);
    }

    private function draft(array $extra = []): TelegramBroadcast
    {
        return TelegramBroadcast::create($extra + [
            'source_chat_id' => self::ADMIN_CHAT, 'source_message_ids' => [10], 'text' => 'Привет всем', 'card_message_id' => self::CARD,
        ]);
    }

    private function topicMessage(int $messageId, ?string $text, int $threadId = self::TOPIC): array
    {
        return ['update_id' => $messageId, 'message' => array_filter([
            'message_id' => $messageId,
            'message_thread_id' => $threadId,
            'is_topic_message' => true,
            'from' => ['id' => 1, 'is_bot' => false, 'first_name' => 'Админ'],
            'chat' => ['id' => self::ADMIN_CHAT, 'type' => 'supergroup'],
            'reply_to_message' => ['message_id' => $threadId, 'from' => ['id' => 1, 'is_bot' => false]],
            'text' => $text,
        ], fn ($v) => $v !== null)];
    }

    private function replyToCard(int $messageId, string $text): array
    {
        $m = $this->topicMessage($messageId, $text);
        $m['message']['reply_to_message'] = ['message_id' => self::CARD, 'from' => ['id' => 99, 'is_bot' => true]];

        return $m;
    }

    private function press(TelegramBroadcast $broadcast, string $action): array
    {
        return ['update_id' => random_int(1000, 9999), 'callback_query' => [
            'id' => 'cb'.random_int(1, 999),
            'from' => ['id' => 1, 'is_bot' => false, 'first_name' => 'Админ'],
            'message' => ['message_id' => self::CARD, 'chat' => ['id' => self::ADMIN_CHAT, 'type' => 'supergroup']],
            'data' => "bc:{$broadcast->id}:{$action}",
        ]];
    }

    private function to(Request $r, string $bot, string $method): bool
    {
        return str_ends_with($r->url(), '/bot'.$bot.'-token/'.$method);
    }

    private function countSent(string $method, ?callable $filter = null, ?string $bot = null): int
    {
        return Http::recorded(fn (Request $r) => ($bot ? $this->to($r, $bot, $method) : str_ends_with($r->url(), '/'.$method))
            && (! $filter || $filter($r)))->count();
    }
}
