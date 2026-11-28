<?php

namespace Tests\Feature\LiveChat;

use App\Models\Course;
use App\Models\CourseSession;
use App\Models\Lesson;
use App\Models\LessonChat;
use App\Models\User;
use App\Service\EnrollmentService;
use App\Service\LiveChat\LiveChatService;
use Tests\TestCase;

/** Чат трансляции, шаг 2: эмоции, «Пон / Не пон», перерыв, тишина, онлайн, гости. */
class LiveChatRoomTest extends TestCase
{
    private Lesson $lesson;

    private Course $course;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['live_chat.enabled' => true]);
        $this->course = Course::create(['title' => 'Курс', 'description' => 'Тест', 'price_cents' => 100000]);
        $session = CourseSession::create([
            'course_id' => $this->course->id, 'date' => now()->toDateString(),
            'start_time' => now()->subMinutes(5)->format('H:i:s'), 'end_time' => now()->addMinutes(85)->format('H:i:s'),
            'duration_minutes' => 90, 'status' => 'active',
        ]);
        $this->lesson = Lesson::create(['course_session_id' => $session->id, 'title' => 'Урок', 'meet_link' => 'live0abc123']);
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'chat_name' => 'Александр Полтавский']);
    }

    /** Ученик курса; $fed — сколько скормил акуле (уровень: 120 → 3-й, 400 → 4-й). */
    private function student(string $first, int $fed = 0): User
    {
        $u = User::factory()->create(['role' => User::ROLE_READER, 'first_name' => $first, 'last_name' => 'Тестов', 'fish_total_fed' => $fed]);
        app(EnrollmentService::class)->enrollUser($u, $this->course, ['source' => 'manual']);

        return $u;
    }

    private function feed(User $u, int $v = 0, bool $presence = false): array
    {
        $k = app(LiveChatService::class)->feedToken($u, $this->lesson);

        return $this->getJson(route('live-chat.feed', $this->lesson).'?k='.$k.'&v='.$v.($presence ? '&p=1' : ''))->assertOk()->json();
    }

    private function as(User $u, string $method, string $route, array $data = [], array $params = [])
    {
        return $this->actingAs($u)->json($method, route($route, array_merge([$this->lesson], $params)), $data);
    }

    public function test_emotions_level_support_limit_and_cooldown(): void
    {
        $low = $this->student('Миша', 0);
        $this->as($low, 'POST', 'live-chat.emotion', ['code' => 'brain'])->assertStatus(422)->assertJsonPath('error', 'level');

        $a = $this->student('Аня', 120);
        $b = $this->student('Боря', 120);
        $c = $this->student('Вика', 120);
        $d = $this->student('Гоша', 120);
        $this->as($a, 'POST', 'live-chat.emotion', ['code' => 'brain'])->assertOk();
        // та же эмоция уже висит — это поддержка, а не новая
        $this->as($b, 'POST', 'live-chat.emotion', ['code' => 'brain'])->assertOk();
        $emo = $this->feed($low)['emo'];
        $this->assertCount(1, $emo);
        $this->assertSame(1, $emo[0]['c']);
        $this->assertSame(['Боря Т.'], $emo[0]['names']);

        $this->as($c, 'POST', 'live-chat.emotion', ['code' => 'slow'])->assertOk();
        $this->as($d, 'POST', 'live-chat.emotion', ['code' => 'fire'])->assertOk();
        $this->as($b, 'POST', 'live-chat.emotion', ['code' => 'lol'])->assertStatus(422)->assertJsonPath('message', 'Максимум эмоций на экране');

        // через минуту эмоции исчезли, но у Ани ещё не прошло 5 минут
        $this->travel(61)->seconds();
        $this->assertSame([], $this->feed($low)['emo']);
        $this->as($a, 'POST', 'live-chat.emotion', ['code' => 'lol'])->assertStatus(422)->assertJsonPath('error', 'cooldown');
        $this->travel(5)->minutes();
        $this->as($a, 'POST', 'live-chat.emotion', ['code' => 'lol'])->assertOk();
    }

    public function test_poll_understand_votes_names_and_hides_emotions(): void
    {
        $a = $this->student('Аня', 120);
        $b = $this->student('Боря');
        $this->as($a, 'POST', 'live-chat.emotion', ['code' => 'brain'])->assertOk();

        $this->as($b, 'POST', 'live-chat.poll.start')->assertForbidden();
        $this->as($this->admin, 'POST', 'live-chat.poll.start')->assertOk();
        $this->as($a, 'POST', 'live-chat.emotion', ['code' => 'fire'])->assertStatus(422)->assertJsonPath('error', 'poll');

        $this->as($a, 'POST', 'live-chat.vote', ['choice' => true])->assertOk();
        $this->as($b, 'POST', 'live-chat.vote', ['choice' => false])->assertOk();
        $this->as($b, 'POST', 'live-chat.vote', ['choice' => true])->assertStatus(422)->assertJsonPath('error', 'voted');

        $f = $this->feed($b);
        $this->assertSame([], $f['emo']); // опрос важнее эмоций
        $this->assertTrue($f['poll']['active']);
        $this->assertSame([1, 1, false], [$f['poll']['yes'], $f['poll']['no'], $f['poll']['mine']]);
        $this->assertSame(['Боря Т.'], $f['poll']['noNames']);

        $this->as($this->admin, 'DELETE', 'live-chat.poll.close')->assertOk();
        $this->assertFalse($this->feed($b)['poll']['active']);
        $this->travel(20)->seconds();
        $this->assertNull($this->feed($b)['poll']);
    }

    public function test_break_quiet_online_and_stats(): void
    {
        $a = $this->student('Аня');
        $this->as($this->admin, 'POST', 'live-chat.quiet', ['minutes' => 5])->assertOk();
        $this->as($this->admin, 'POST', 'live-chat.break', ['minutes' => 10])->assertOk();
        $this->as($a, 'POST', 'live-chat.quiet', ['minutes' => 5])->assertForbidden();

        $this->as($a, 'POST', 'live-chat.send', ['text' => 'привет'])->assertStatus(422)->assertJsonPath('error', 'quiet');
        $this->as($a, 'POST', 'live-chat.send', ['text' => 'А это будет на ЕГЭ?', 'question' => true])->assertOk();
        $this->as($this->admin, 'POST', 'live-chat.send', ['text' => 'Тишина, работаем'])->assertOk();

        $room = $this->feed($a, 0, true)['room'];
        $this->assertNotNull($room['quietUntil']);
        $this->assertNotNull($room['breakUntil']);
        $this->assertSame(1, $this->feed($this->admin)['online']);
        $this->assertArrayNotHasKey('online', $this->feed($a));

        $this->as($this->admin, 'POST', 'live-chat.quiet', ['minutes' => 0])->assertOk();
        $this->as($a, 'POST', 'live-chat.send', ['text' => 'привет'])->assertOk();

        $k = fn ($u) => app(LiveChatService::class)->feedToken($u, $this->lesson);
        $this->getJson(route('live-chat.stats', $this->lesson).'?k='.$k($a))->assertForbidden();
        $stats = $this->getJson(route('live-chat.stats', $this->lesson).'?k='.$k($this->admin))->assertOk()->json();
        $this->assertSame(1, $stats['questions']);
        $this->assertSame([['name' => 'Аня Т.', 'c' => 2]], $stats['top']);
    }

    public function test_student_report_shows_chat_activity(): void
    {
        $a = $this->student('Аня', 120);
        $this->as($a, 'POST', 'live-chat.send', ['text' => 'А почему 4?', 'question' => true])->assertOk();
        $this->as($a, 'POST', 'live-chat.emotion', ['code' => 'brain'])->assertOk();
        $this->as($this->admin, 'POST', 'live-chat.poll.start')->assertOk();
        $this->as($a, 'POST', 'live-chat.vote', ['choice' => false])->assertOk();

        $this->actingAs($this->admin)->get(route('admin.user.show', $a))->assertOk()
            ->assertSee('чат: 1 сообщ., 1 вопр., 1 эмоц.')
            ->assertSee('«пон / не пон»: 0 / 1', false);
    }

    public function test_emoji_in_name_from_level_four(): void
    {
        $low = $this->student('Аня', 120);
        $high = $this->student('Боря', 400);
        $this->as($low, 'POST', 'live-chat.name', ['name' => 'Аня 🦈'])->assertOk()->assertJsonPath('name', 'Аня');
        $this->as($high, 'POST', 'live-chat.name', ['name' => 'Боря 🦈'])->assertOk()->assertJsonPath('name', 'Боря 🦈');
    }

    public function test_guest_link_page_writes_as_guest_and_can_be_muted(): void
    {
        $this->actingAs($this->admin)->post(route('admin.lessons.guest-link', $this->lesson))->assertRedirect();
        $token = LessonChat::where('lesson_id', $this->lesson->id)->value('guest_token');
        $this->assertNotNull($token);

        auth()->logout();
        $this->get('/live/'.$token)->assertOk()->assertSee('data-live-chat', false)->assertSee('Гость');
        $this->get('/live/wrongtoken123456789')->assertNotFound();

        $this->postJson('/live/'.$token.'/name', ['name' => 'Мама Ани 🌸'])->assertOk()->assertJsonPath('name', 'Мама Ани');
        $msg = $this->postJson('/live/'.$token.'/messages', ['text' => 'Здравствуйте, я на пробном'])->assertOk()->json('msg');
        $this->assertSame('Гость', $msg['b']);
        $this->assertGreaterThanOrEqual(LiveChatService::GUEST_BASE, $msg['uid']);
        $this->assertDatabaseMissing('users', ['first_name' => 'Мама Ани']);

        $seen = collect($this->feed($this->admin)['msgs'])->firstWhere('id', $msg['id']);
        $this->assertSame('Гость по ссылке, не зарегистрирован', $seen['real']);

        $this->as($this->admin, 'POST', 'live-chat.mute', ['duration' => 'lesson'], [$msg['uid']])->assertOk();
        auth()->logout();
        $this->postJson('/live/'.$token.'/messages', ['text' => 'ещё'])->assertStatus(422)->assertJsonPath('error', 'muted');
    }
}
