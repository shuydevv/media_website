<?php

namespace Tests\Feature\LiveChat;

use App\Models\ChatMessage;
use App\Models\ChatReactionType;
use App\Models\Course;
use App\Models\CourseSession;
use App\Models\Lesson;
use App\Models\User;
use App\Service\EnrollmentService;
use App\Service\LiveChat\LiveChatService;
use Tests\TestCase;

/** Чат трансляции: от отправки до ленты, модерации и сигналов Kinescope. */
class LiveChatFlowTest extends TestCase
{
    private Lesson $lesson;

    private User $student;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['live_chat.kinescope.webhook_secret' => 'sekret', 'live_chat.enabled' => true]);

        $course = Course::create(['title' => 'Курс', 'description' => 'Тест', 'price_cents' => 100000]);
        $session = CourseSession::create([
            'course_id' => $course->id,
            'date' => now()->toDateString(),
            'start_time' => now()->addMinutes(5)->format('H:i:s'),
            'end_time' => now()->addMinutes(95)->format('H:i:s'),
            'duration_minutes' => 90,
            'status' => 'active',
        ]);
        $this->lesson = Lesson::create(['course_session_id' => $session->id, 'title' => 'Урок', 'meet_link' => 'live0abc123']);

        $this->student = User::factory()->create(['role' => User::ROLE_READER, 'first_name' => 'Аня', 'last_name' => 'Котова']);
        app(EnrollmentService::class)->enrollUser($this->student, $course, ['source' => 'manual']);
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'chat_name' => 'Александр Полтавский', 'chat_badge' => 'Преподаватель']);
    }

    private function send(User $u, string $text, array $extra = [])
    {
        return $this->actingAs($u)->postJson(route('live-chat.send', $this->lesson), ['text' => $text] + $extra);
    }

    private function feed(User $u, int $v = 0): array
    {
        $k = app(LiveChatService::class)->feedToken($u, $this->lesson);

        return $this->getJson(route('live-chat.feed', $this->lesson).'?k='.$k.'&v='.$v)->assertOk()->json();
    }

    public function test_message_flow_versions_and_names(): void
    {
        $this->send($this->student, 'Здравствуйте!')->assertOk()->assertJsonPath('msg.n', 'Аня К.');
        $this->send($this->admin, 'Привет всем')->assertOk()
            ->assertJsonPath('msg.n', 'Александр Полтавский')->assertJsonPath('msg.b', 'Преподаватель');

        $first = $this->feed($this->student);
        $this->assertSame('open', $first['state']['s']);
        $this->assertCount(2, $first['msgs']);
        $this->assertArrayNotHasKey('real', $first['msgs'][0]); // ученик не видит ФИ

        $adminView = $this->feed($this->admin);
        $this->assertSame('Аня Котова', $adminView['msgs'][0]['real']);

        // Ничего не менялось — пустой ответ без сообщений.
        $same = $this->feed($this->student, $first['v']);
        $this->assertArrayNotHasKey('msgs', $same);

        // Имя меняется — новые сообщения под новым, старые под старым.
        $this->actingAs($this->student)->postJson(route('live-chat.name', $this->lesson), ['name' => 'Анюта'])->assertOk();
        $this->send($this->student, 'Это я');
        $delta = $this->feed($this->student, $first['v']);
        $this->assertCount(1, $delta['msgs']);
        $this->assertSame('Анюта', $delta['msgs'][0]['n']);
        $this->assertSame('Аня К.', ChatMessage::orderBy('id')->first()->author_name);
    }

    public function test_reply_question_answer_and_reaction_toggle(): void
    {
        $q = $this->send($this->student, 'Почему 4?', ['question' => true])->json('msg');
        $this->assertTrue($q['q']);
        // Ответ уходит в ветку вопроса, в общей ленте его нет.
        $reply = $this->send($this->admin, 'Потому что', ['thread' => $q['id']])->json('msg');
        $this->assertSame($q['id'], $reply['th']);
        $this->assertNull($reply['reply']);
        $quote = $this->send($this->student, 'А почему?', ['thread' => $q['id'], 'reply_to' => $reply['id']])->json('msg');
        $this->assertSame($this->admin->id, $quote['reply']['uid']);
        $this->assertSame([$q['id']], array_column($this->feed($this->student)['msgs'], 'id'));
        $k = app(LiveChatService::class)->feedToken($this->student, $this->lesson);
        $thread = $this->getJson(route('live-chat.thread', $this->lesson).'?k='.$k.'&root='.$q['id'])->json();
        $this->assertCount(2, $thread['msgs']);
        $this->assertSame(2, $thread['root']['tc']['c']);
        $this->assertTrue($thread['root']['tc']['teach']);

        // Преподаватель ответил в ветке вопроса — «есть ответ» поставился сам.
        $this->assertTrue(collect($this->feed($this->student)['msgs'])->firstWhere('id', $q['id'])['ans']);
        $this->actingAs($this->student)->postJson(route('live-chat.answer', [$this->lesson, $q['id']]))->assertForbidden();
        $this->actingAs($this->admin)->postJson(route('live-chat.answer', [$this->lesson, $q['id']]), ['answered' => false])->assertOk();
        $this->actingAs($this->admin)->postJson(route('live-chat.answer', [$this->lesson, $q['id']]))->assertOk();

        $heart = ChatReactionType::where('noto_code', '2764_fe0f')->value('id');
        $this->actingAs($this->student)->postJson(route('live-chat.react', [$this->lesson, $q['id']]), ['reaction' => $heart])->assertOk();
        $msg = collect($this->feed($this->student)['msgs'])->firstWhere('id', $q['id']);
        $this->assertTrue($msg['ans']);
        $this->assertSame([[$heart, 1, true]], $msg['rx']);

        $this->actingAs($this->student)->postJson(route('live-chat.react', [$this->lesson, $q['id']]), ['reaction' => $heart])->assertOk();
        $this->assertSame([], collect($this->feed($this->student)['msgs'])->firstWhere('id', $q['id'])['rx']);

        // Ученик — не больше 3 реакций на сообщение: четвёртая вытесняет первую.
        $types = ChatReactionType::orderBy('sort')->limit(4)->pluck('id')->all();
        foreach ($types as $t) {
            $this->actingAs($this->student)->postJson(route('live-chat.react', [$this->lesson, $q['id']]), ['reaction' => $t])->assertOk();
        }
        $rx = collect($this->feed($this->student)['msgs'])->firstWhere('id', $q['id'])['rx'];
        $this->assertSame(array_slice($types, 1), array_column($rx, 0));
        foreach (array_slice($types, 1) as $t) {
            $this->actingAs($this->student)->postJson(route('live-chat.react', [$this->lesson, $q['id']]), ['reaction' => $t])->assertOk();
        }

        // Реакция преподавателя — отдельно, в общий счётчик не входит.
        $this->actingAs($this->admin)->postJson(route('live-chat.react', [$this->lesson, $q['id']]), ['reaction' => $heart])->assertOk();
        $msg = collect($this->feed($this->student)['msgs'])->firstWhere('id', $q['id']);
        $this->assertSame([], $msg['rx']);
        $this->assertSame([$heart, $this->admin->id, 'Александр Полтавский'], array_slice($msg['tr'][0], 0, 3));
    }

    public function test_flood_mute_and_demo_are_refused(): void
    {
        foreach (range(1, 5) as $i) {
            $this->send($this->student, 'msg '.$i)->assertOk();
        }
        $this->send($this->student, 'ещё')->assertStatus(422)->assertJsonPath('error', 'slow');

        $this->actingAs($this->student)->postJson(route('live-chat.mute', [$this->lesson, $this->student]), ['duration' => '10m'])->assertForbidden();
        $this->actingAs($this->admin)->postJson(route('live-chat.mute', [$this->lesson, $this->student]), ['duration' => 'lesson'])->assertOk();
        $this->travel(1)->minutes();
        $this->send($this->student, 'а теперь?')->assertStatus(422)->assertJsonPath('error', 'muted');
        $this->assertFalse($this->feed($this->student)['me']['write']);

        $this->actingAs($this->admin)->deleteJson(route('live-chat.unmute', [$this->lesson, $this->student]))->assertOk();
        $this->travel(3)->minutes();
        $this->send($this->student, 'снова пишу')->assertOk();

        $this->student->forceFill(['is_demo' => true])->save();
        $this->send($this->student, 'демо')->assertStatus(422)->assertJsonPath('error', 'demo');
    }

    public function test_deleted_message_reaches_clients_as_deleted(): void
    {
        $m = $this->send($this->student, 'плохое слово')->json('msg');
        $v = $this->feed($this->admin)['v'];
        $this->actingAs($this->admin)->deleteJson(route('live-chat.delete', [$this->lesson, $m['id']]))->assertOk();
        $this->assertSame([['id' => $m['id'], 'v' => $v + 1, 'del' => true]], $this->feed($this->student, $v)['msgs']);
    }

    public function test_feed_needs_valid_token_and_course_access(): void
    {
        $this->getJson(route('live-chat.feed', $this->lesson).'?k=1.9999999999.'.str_repeat('a', 64))->assertUnauthorized();

        $stranger = User::factory()->create(['role' => User::ROLE_READER]);
        $this->send($stranger, 'я не на курсе')->assertForbidden();
    }

    public function test_lesson_page_shows_live_chat_then_replay_next_to_recording(): void
    {
        $this->actingAs($this->student)->get(route('student.lessons.show', $this->lesson))
            ->assertOk()->assertSee('data-live-chat', false)->assertSee('&quot;mode&quot;:&quot;live&quot;', false)
            ->assertDontSee('kinescope.io/chat/', false);

        $this->send($this->student, 'привет');
        $signals = app(\App\Service\LiveChat\LiveSignals::class);
        $signals->start($this->lesson);
        $signals->end($this->lesson);
        $this->travel(11)->minutes();
        $this->lesson->update(['recording_link' => 'rec0abc123']);

        $this->actingAs($this->student)->get(route('student.lessons.show', $this->lesson))
            ->assertOk()->assertSee('&quot;mode&quot;:&quot;replay&quot;', false);
        $k = app(LiveChatService::class)->feedToken($this->student, $this->lesson);
        $replay = $this->getJson(route('live-chat.replay', $this->lesson).'?k='.$k)->assertOk()->json();
        $this->assertSame(0, $replay['msgs'][0]['t']); // написано до эфира — в начале записи

        config(['live_chat.enabled' => false]);
        $this->actingAs($this->student)->get(route('student.lessons.show', $this->lesson))
            ->assertOk()->assertSee('kinescope.io/chat/live0abc123', false);
    }

    public function test_console_is_for_team_only(): void
    {
        $this->actingAs($this->student)->get(route('live-chat.console.lesson', $this->lesson))->assertForbidden();
        $this->actingAs($this->admin)->get(route('live-chat.console.lesson', $this->lesson))->assertOk()->assertSee('data-live-chat', false);
        $this->actingAs($this->admin)->get(route('live-chat.console'))->assertOk()->assertSee('Урок');
    }

    public function test_sandbox_is_for_team_only_and_touches_no_data(): void
    {
        config(['live_chat.enabled' => false]);
        $this->actingAs($this->student)->get(route('live-chat.sandbox'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('live-chat.sandbox'))->assertOk()
            ->assertSee('/__sandbox/feed', false)->assertSee('live-chat-sandbox.js', false);
        $this->actingAs($this->admin)->get(route('live-chat.sandbox', ['as' => 'student']))->assertOk();
        $this->assertDatabaseCount('chat_messages', 0);
    }

    public function test_kinescope_signals_open_break_and_close(): void
    {
        $this->travel(-2)->hours(); // до урока: чат ждёт
        $this->assertSame('waiting', $this->feed($this->student)['state']['s']);
        $this->postJson('/api/kinescope/webhook/sekret', ['event' => 'live.connected', 'data' => ['id' => 'live0abc123']])->assertOk();
        $this->assertSame('open', $this->feed($this->student)['state']['s']);

        $this->postJson('/api/kinescope/webhook/sekret', ['event' => 'live.finished', 'data' => ['id' => 'live0abc123']])->assertOk();
        $this->assertSame('break', $this->feed($this->student)['state']['s']);
        $this->travel(11)->minutes();
        $this->assertSame('closed', $this->feed($this->student)['state']['s']);
        $this->send($this->student, 'поздно')->assertStatus(422)->assertJsonPath('error', 'closed');

        $this->postJson('/api/kinescope/webhook/wrong', ['event' => 'live.connected'])->assertNotFound();
        $this->assertDatabaseCount('kinescope_webhook_events', 2);
    }
}
