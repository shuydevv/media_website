<?php

namespace Tests\Feature\Diagnostic;

use App\Models\DiagnosticAnswer;
use App\Models\DiagnosticAttempt;
use App\Models\TelegramContact;
use App\Service\Diagnostic\AnswerChecker;
use App\Service\Diagnostic\QuestionBank;
use App\Service\Telegram\TelegramBots;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Прохождение диагностики через API страницы (Diagnostic\DiagnosticController). */
class DiagnosticFlowTest extends TestCase
{
    use RefreshDatabase;

    private const DIAG_TOKEN = '123456:diag-token';

    private ?string $attempt = null;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'diagnostic.allow_anonymous' => true,
            'services.telegram_diag.bot_token' => self::DIAG_TOKEN,
        ]);
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        // События теста уходят боту (DiagBot) — очередь в тестах синхронная,
        // наружу ничего не шлём.
        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 1, 'message_thread_id' => 9]])]);
    }

    public function test_page_opens(): void
    {
        $this->get('/diag')->assertOk()->assertSee('telegram-web-app.js', false)->assertSee('js/diagnostic.js', false);
    }

    public function test_all_correct_walks_every_block_to_the_top(): void
    {
        $state = $this->openSession()->assertOk()->assertJsonPath('screen', 'profile')->json();
        $this->assertSame(5, $state['progress']['total']);

        $state = $this->call_('profile', ['grade' => '11'])->assertJsonPath('screen', 'question')->json();
        $this->assertArrayNotHasKey('correct', $state['question']);
        $this->assertArrayNotHasKey('answer', $state['question']);
        $this->assertArrayNotHasKey('explain_correct', $state['question']);

        $answers = 0;
        while ($state['screen'] === 'question') {
            $res = $this->call_('answer', ['question_id' => $state['question']['id'], 'answer' => $this->right($state['question']['id']), 'answer_ms' => 1500])
                ->assertOk()->assertJsonPath('correct', true)->json();
            $state = $res['state'];
            $answers++;
        }

        $this->assertSame(20, $answers, '5 блоков × 4 вопроса');
        $this->assertSame('target', $state['screen']);

        $result = $this->call_('target', ['target' => 85])->assertJsonPath('screen', 'result')->json('result');
        $this->assertSame(85, $result['target']);
        $this->assertSame(0, $result['guessed']);
        $this->assertSame([], $result['weak'], 'всё на сложном — слабых блоков нет');

        $attempt = DiagnosticAttempt::sole();
        $this->assertTrue($attempt->isFinished());
        $this->assertSame(array_fill_keys(array_keys(config('diagnostic.blocks')), 3), $attempt->levels);
        $this->assertSame('11', $attempt->grade);
    }

    public function test_all_wrong_closes_each_block_after_two_questions(): void
    {
        $this->openSession();
        $state = $this->call_('profile', ['grade' => '10'])->json();

        $closed = 0;
        while ($state['screen'] === 'question') {
            $res = $this->call_('answer', ['question_id' => $state['question']['id'], 'answer' => ['wrong' => true]])
                ->assertJsonPath('correct', false)->json();
            $closed += $res['block']['closed_by_errors'] ? 1 : 0;
            $state = $res['state'];
        }

        $this->assertSame(5, $closed);
        $this->assertSame(10, DiagnosticAnswer::count());

        $result = $this->call_('target', ['target' => null])->json('result');
        $this->assertNull($result['target']);
        $this->assertCount(2, $result['weak']);
        $this->assertGreaterThan(0, $result['potential'] - $result['high']);
    }

    public function test_guess_is_logged_and_counted(): void
    {
        $this->openSession();
        $state = $this->call_('profile', ['grade' => '11'])->json();
        while ($state['screen'] === 'question') {
            $state = $this->call_('answer', ['question_id' => $state['question']['id'], 'answer' => $this->right($state['question']['id']), 'guess' => true])->json('state');
        }

        $result = $this->call_('target', ['target' => 70])->json('result');

        $this->assertSame(20, $result['guessed']);
        $attempt = DiagnosticAttempt::sole();
        $this->assertSame(array_fill_keys(array_keys(config('diagnostic.blocks')), 0), $attempt->levels_low);
        $this->assertLessThan($attempt->result['high'] - 2 * config('diagnostic.model.min_spread'), $attempt->result['low']);
    }

    public function test_answer_to_old_question_returns_current_state(): void
    {
        $this->openSession();
        $state = $this->call_('profile', ['grade' => '11'])->json();
        $id = $state['question']['id'];
        $this->call_('answer', ['question_id' => $id, 'answer' => $this->right($id)])->assertOk();

        $this->call_('answer', ['question_id' => $id, 'answer' => $this->right($id)])
            ->assertStatus(409)->assertJsonPath('stale', true)->assertJsonPath('state.screen', 'question');
        $this->assertSame(1, DiagnosticAnswer::count(), 'повтор не записан');
    }

    public function test_explain_time_goes_to_previous_answer(): void
    {
        $this->openSession();
        $state = $this->call_('profile', ['grade' => '11'])->json();
        $first = $state['question']['id'];
        $state = $this->call_('answer', ['question_id' => $first, 'answer' => $this->right($first)])->json('state');
        $this->call_('answer', ['question_id' => $state['question']['id'], 'answer' => ['x' => 1], 'explain_ms' => 4200]);

        $this->assertSame(4200, DiagnosticAnswer::orderBy('id')->first()->explain_ms);
    }

    public function test_improve_answer_is_recorded_once(): void
    {
        $this->finishedAttempt();

        $this->call_('improve', ['yes' => true])->assertJsonPath('improve', 'yes');
        $this->call_('improve', ['yes' => false])->assertJsonPath('improve', 'yes');

        $attempt = DiagnosticAttempt::sole();
        $this->assertSame('page', $attempt->improve_source);
        $this->assertNotNull($attempt->improve_at);
    }

    public function test_without_telegram_or_link_is_refused_on_prod(): void
    {
        config(['diagnostic.allow_anonymous' => false]);

        $this->openSession()->assertForbidden()->assertJsonPath('error', 'open_in_telegram');
        $this->openSession(['init_data' => 'user=%7B%22id%22%3A1%7D&auth_date=1&hash=bad'])->assertForbidden()->assertJsonPath('error', 'auth');
    }

    public function test_telegram_user_gets_contact_of_diag_bot_and_resumes(): void
    {
        config(['diagnostic.allow_anonymous' => false]);
        $lesson = TelegramContact::create([
            'bot' => TelegramBots::LESSON, 'telegram_user_id' => 555, 'lesson' => 'cho', 'link_token' => 'lessontoken12345',
            'started_at' => now(), 'last_start_at' => now(),
        ]);

        $state = $this->openSession(['init_data' => $this->initData(555)])->assertOk()->assertJsonPath('in_telegram', true)->json();
        $this->attempt = $state['attempt'];
        $question = $this->call_('profile', ['grade' => 'graduate'])->json('question.id');

        // Закрыл и открыл заново — тот же вопрос того же прохождения.
        $this->attempt = null;
        $again = $this->openSession(['init_data' => $this->initData(555)])->json();
        $this->assertSame($state['attempt'], $again['attempt']);
        $this->assertSame($question, $again['question']['id']);

        $contact = TelegramContact::forBot(TelegramBots::DIAG)->sole();
        $this->assertSame(555, (int) $contact->telegram_user_id);
        $this->assertNotSame($lesson->id, $contact->id);
        $this->assertSame($contact->id, DiagnosticAttempt::sole()->telegram_contact_id);
        $this->assertSame('miniapp', DiagnosticAttempt::sole()->entry);
    }

    public function test_restart_only_after_finish_and_starts_from_the_beginning(): void
    {
        config(['diagnostic.allow_anonymous' => false]);
        $init = $this->initData(777);
        $this->attempt = $this->openSession(['init_data' => $init])->json('attempt');
        $this->call_('profile', ['grade' => '10']);

        $this->assertSame($this->attempt, $this->openSession(['init_data' => $init, 'restart' => true])->json('attempt'), 'незаконченное не сбрасываем');

        $this->walkCorrect();
        $this->call_('target', ['target' => 80]);
        $old = $this->attempt;
        $new = $this->openSession(['init_data' => $init, 'restart' => true])->assertJsonPath('screen', 'profile')->json();

        $this->assertNotSame($old, $new['attempt']);
        $this->assertNull(DiagnosticAttempt::latest('id')->first()->grade, 'заново — с вопроса про класс');
    }

    public function test_restart_mid_test_abandons_attempt_and_starts_fresh(): void
    {
        $this->openSession();
        $state = $this->call_('profile', ['grade' => '11'])->json();
        $this->call_('answer', ['question_id' => $state['question']['id'], 'answer' => ['x' => 1]]);
        $old = $this->attempt;

        // Заново — с самого начала: снова знакомство («Ты в каком классе?»).
        $new = $this->call_('restart', [])->assertOk()->assertJsonPath('screen', 'profile')->json();

        $this->assertNotSame($old, $new['attempt']);
        $this->assertSame(DiagnosticAttempt::STATUS_ABANDONED, DiagnosticAttempt::where('token', $old)->value('status'));
        $this->assertSame(0, $new['progress']['index']);
        $this->assertNull(DiagnosticAttempt::where('token', $new['attempt'])->value('grade'));
    }

    public function test_restart_after_result_keeps_finished_attempt(): void
    {
        $this->finishedAttempt();
        $old = $this->attempt;

        $this->call_('restart', [])->assertOk();

        $this->assertSame(DiagnosticAttempt::STATUS_FINISHED, DiagnosticAttempt::where('token', $old)->value('status'));
        $this->assertSame(2, DiagnosticAttempt::count());
    }

    public function test_who_explanation_is_voiced_by_the_right_speaker(): void
    {
        $this->openSession();
        $this->call_('profile', ['grade' => '11']);
        // Текущим ставим «Кто прав?» из банка — на случайном пути его может не быть.
        $q = collect(QuestionBank::fromConfig()->all())->firstWhere('skin', 'who');
        DiagnosticAttempt::where('token', $this->attempt)->update(['current_question_id' => $q['id']]);

        $res = $this->call_('answer', ['question_id' => $q['id'], 'answer' => $this->right($q['id'])])->assertOk()->json();

        $right = collect($q['speakers'])->firstWhere('id', $q['correct']);
        $this->assertSame($right['who'], $res['explain_by']);
    }

    public function test_api_needs_attempt_token(): void
    {
        $this->postJson('/diag/api/profile', ['grade' => '11'])->assertForbidden();
        $this->postJson('/diag/api/profile', ['grade' => '11'], ['X-Diag-Attempt' => 'nope'])->assertNotFound();
    }

    // ---------- помощники ----------

    private function openSession(array $body = []): TestResponse
    {
        $response = $this->postJson('/diag/api/session', $body);
        if ($response->status() === 200) {
            $this->attempt = $response->json('attempt');
        }

        return $response;
    }

    private function call_(string $action, array $body): TestResponse
    {
        return $this->postJson('/diag/api/'.$action, $body, ['X-Diag-Attempt' => $this->attempt]);
    }

    private function right(string $questionId): array
    {
        return AnswerChecker::solution(QuestionBank::fromConfig()->get($questionId));
    }

    private function walkCorrect(): void
    {
        $state = $this->call_('profile', ['grade' => '10'])->json();
        while ($state['screen'] === 'question') {
            $state = $this->call_('answer', ['question_id' => $state['question']['id'], 'answer' => $this->right($state['question']['id'])])->json('state');
        }
    }

    private function finishedAttempt(): void
    {
        $this->openSession();
        $this->walkCorrect();
        $this->call_('target', ['target' => 80]);
    }

    private function initData(int $userId): string
    {
        $fields = [
            'auth_date' => (string) time(),
            'query_id' => 'AAH',
            'user' => json_encode(['id' => $userId, 'first_name' => 'Тест'], JSON_UNESCAPED_UNICODE),
        ];
        ksort($fields);
        $check = implode("\n", array_map(fn ($k, $v) => "{$k}={$v}", array_keys($fields), $fields));
        $fields['hash'] = hash_hmac('sha256', $check, hash_hmac('sha256', self::DIAG_TOKEN, 'WebAppData', true));

        return http_build_query($fields);
    }
}
