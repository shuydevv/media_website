<?php

namespace Tests\Feature\Student;

use App\Models\Statement;
use App\Models\StatementSession;
use App\Models\User;
use App\Models\UserStatement;
use App\Service\Statements\StatementImporter;
use App\Service\Trainers\StatementsTrainerCard;
use App\Service\Trainers\Trainer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «Верные суждения» через HTTP: доступ, сессия до итога, повтор ошибок
 * на следующий день, «Повторить ошибки», корм и его дневной лимит.
 */
class StatementTrainerFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-06 12:00:00');

        // По 20 суждений на блок, верные и неверные через одно; ещё одно —
        // без пояснения (в тренажёр не идёт).
        $rows = [];
        foreach (array_keys(config('statements.blocks')) as $b => $block) {
            for ($i = 1; $i <= 20; $i++) {
                $rows[] = [
                    'id' => "{$block}-{$i}",
                    'block' => $block,
                    'about' => 'о чём-то',
                    'text' => "Суждение {$block} {$i}.",
                    'is_true' => $i % 2 === 0,
                    'explanation' => "Пояснение {$i}.",
                    'status' => 'active',
                    'source' => ['task' => $b * 100 + intdiv($i - 1, 5), 'line' => 2, 'theme' => 'тема'],
                ];
            }
        }
        $rows[] = ['id' => 'draft-1', 'block' => 'law', 'text' => 'Черновик.', 'is_true' => true, 'status' => 'no_explanation'];
        app(StatementImporter::class)->apply(['statements' => $rows]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function student(): User
    {
        $user = User::factory()->create(['role' => User::ROLE_READER, 'fish_corm_balance' => 0]);
        DB::table('term_trainer_users')->insert(['user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    private function start(User $user, array $blocks): StatementSession
    {
        $this->actingAs($user)->post(route('student.statements.start'), ['blocks' => $blocks])->assertRedirect();

        return StatementSession::where('user_id', $user->id)->latest('id')->firstOrFail();
    }

    /** Отвечает на всю сессию: $right(position) — отвечать ли верно. */
    private function answerAll(User $user, StatementSession $session, callable $right): array
    {
        $last = null;
        foreach ($session->items as $position => $item) {
            $isTrue = Statement::find($item['id'])->is_true;
            $answer = $right($position) ? ($isTrue ? 'true' : 'false') : ($isTrue ? 'false' : 'true');
            $last = $this->actingAs($user)
                ->postJson(route('student.statements.session.answer', $session), ['position' => $position, 'answer' => $answer])
                ->assertOk()
                ->json();
        }

        return $last;
    }

    /** @test */
    public function without_access_the_trainer_is_closed()
    {
        $user = User::factory()->create(['role' => User::ROLE_READER]);
        $this->actingAs($user)->get(route('student.statements.index'))->assertForbidden();
    }

    /** @test */
    public function the_index_shows_the_blocks()
    {
        $this->actingAs($this->student())->get(route('student.statements.index'))
            ->assertOk()
            ->assertSee('Какие блоки качаем?')
            ->assertSee('Социальные отношения')
            ->assertSee('Пройдено: 0')
            ->assertDontSee('из 20');
    }

    /** @test */
    public function a_session_takes_only_active_statements_from_chosen_blocks()
    {
        $user = $this->student();
        $session = $this->start($user, ['law', 'economy']);

        $this->assertSame(15, $session->total);
        $blocks = Statement::whereIn('id', array_column($session->items, 'id'))->pluck('block')->unique()->values()->all();
        $this->assertEqualsCanonicalizing(array_intersect(['law', 'economy'], $blocks), $blocks);
        $this->assertNotContains(Statement::where('key', 'draft-1')->value('id'), array_column($session->items, 'id'));

        // Страница сессии не выдаёт ответы.
        $this->actingAs($user)->get(route('student.statements.session', $session))
            ->assertOk()
            ->assertViewHas('boot', fn ($boot) => ! array_key_exists('is_true', $boot['cards'][0])
                && ! array_key_exists('explanation', $boot['cards'][0]))
            ->assertDontSee('Пояснение');
    }

    /** @test */
    public function a_perfect_session_gives_corm_and_nothing_to_repeat()
    {
        $user = $this->student();
        $session = $this->start($user, ['law']);

        $last = $this->answerAll($user, $session, fn () => true);

        $this->assertTrue($last['finished']);
        $this->assertSame(15, $last['summary']['correct']);
        $this->assertSame(3, $last['summary']['corm']);
        $this->assertSame(3, $user->fresh()->fish_corm_balance);
        $this->assertSame(0, UserStatement::where('user_id', $user->id)->whereNotNull('due_on')->count());
        $this->assertSame(Trainer::DONE, app(StatementsTrainerCard::class)->statusFor($user)['situation']);
    }

    /** @test */
    public function mistakes_come_back_tomorrow_first()
    {
        $user = $this->student();
        $this->assertSame(Trainer::NEW, app(StatementsTrainerCard::class)->statusFor($user)['situation']);

        $session = $this->start($user, ['law']);
        $wrongIds = [];
        foreach ($session->items as $p => $item) {
            if ($p < 4) {
                $wrongIds[] = $item['id'];
            }
        }
        $last = $this->answerAll($user, $session, fn ($p) => $p >= 4);

        $this->assertSame(11, $last['summary']['correct']);
        $this->assertCount(4, $last['summary']['mistakes']);
        $this->assertSame(1, $last['summary']['corm']);

        // Сегодня норма сделана, ошибки — завтра.
        $status = app(StatementsTrainerCard::class)->statusFor($user);
        $this->assertSame(Trainer::DONE, $status['situation']);
        $this->assertSame(4, $status['next']);

        Carbon::setTestNow('2026-11-07 09:00:00');
        $status = app(StatementsTrainerCard::class)->statusFor($user);
        $this->assertSame(Trainer::REVIEW, $status['situation']);
        $this->assertSame(4, $status['count']);

        $next = $this->start($user, ['law']);
        $this->assertEqualsCanonicalizing($wrongIds, array_values(array_intersect(array_column($next->items, 'id'), $wrongIds)));
    }

    /** @test */
    public function the_mistakes_replay_does_not_touch_the_schedule_or_corm()
    {
        $user = $this->student();
        $session = $this->start($user, ['law']);
        $this->answerAll($user, $session, fn ($p) => $p >= 3);
        $balance = $user->fresh()->fish_corm_balance;
        $before = UserStatement::where('user_id', $user->id)->orderBy('id')->get(['due_on', 'step'])->toArray();

        $this->actingAs($user)->post(route('student.statements.mistakes', $session))->assertRedirect();
        $replay = StatementSession::where('user_id', $user->id)->latest('id')->first();
        $this->assertSame(StatementSession::KIND_MISTAKES, $replay->kind);
        $this->assertSame(3, $replay->total);

        $last = $this->answerAll($user, $replay, fn () => true);
        $this->assertSame(0, $last['summary']['corm']);
        $this->assertSame($balance, $user->fresh()->fish_corm_balance);
        $this->assertSame($before, UserStatement::where('user_id', $user->id)->orderBy('id')->get(['due_on', 'step'])->toArray());
    }

    /** @test */
    public function corm_is_paid_for_three_sessions_a_day()
    {
        $user = $this->student();
        foreach ([3, 3, 3, 0] as $expected) {
            $session = $this->start($user, ['law', 'economy', 'social', 'politics', 'society']);
            $last = $this->answerAll($user, $session, fn () => true);
            $this->assertSame($expected, $last['summary']['corm']);
        }
        $this->assertSame(9, $user->fresh()->fish_corm_balance);
    }

    /** @test */
    public function the_student_sees_how_many_corm_sessions_are_left()
    {
        $user = $this->student();
        $index = route('student.statements.index');
        $this->actingAs($user)->get($index)->assertSee('Сегодня доступно ещё 3 раунда');

        // Плохой счёт — корма нет, попытка с кормом не потрачена.
        $last = $this->answerAll($user, $this->start($user, ['law']), fn ($p) => $p < 5);
        $this->assertSame('Корм — от 11 из 15. Попытка не сгорела', $last['summary']['corm_note']);
        $this->actingAs($user)->get($index)->assertSee('Сегодня доступно ещё 3 раунда');

        $last = $this->answerAll($user, $this->start($user, ['law', 'economy']), fn () => true);
        $this->assertSame('Ещё 2 раунда с кормом сегодня', $last['summary']['corm_note']);
        $this->actingAs($user)->get($index)->assertSee('Сегодня доступно ещё 2 раунда');

        // В зале норма сделана, но видно, что корм ещё есть.
        $status = app(StatementsTrainerCard::class)->statusFor($user);
        $this->assertSame(Trainer::DONE, $status['situation']);
        $this->actingAs($user)->get(route('student.trainers.index', ['hall' => 'social']))
            ->assertSee('Норма есть ✅ Ещё 2 раунда с кормом');

        $this->answerAll($user, $this->start($user, ['economy', 'social']), fn () => true);
        $last = $this->answerAll($user, $this->start($user, ['social', 'politics']), fn () => true);
        $this->assertSame('Корм на сегодня собран. Завтра — снова', $last['summary']['corm_note']);
        $this->actingAs($user)->get($index)->assertSee('Корм на сегодня собран');
        $this->assertNull(app(StatementsTrainerCard::class)->statusFor($user)['note']);

        $last = $this->answerAll($user, $this->start($user, ['politics', 'society']), fn () => true);
        $this->assertSame('Корм на сегодня уже собран', $last['summary']['corm_note']);
    }

    /** @test */
    public function a_repeated_answer_is_idempotent_and_a_wrong_position_is_stale()
    {
        $user = $this->student();
        $session = $this->start($user, ['law']);
        $url = route('student.statements.session.answer', $session);

        $first = $this->actingAs($user)->postJson($url, ['position' => 0, 'answer' => 'unknown'])->assertOk()->json();
        $again = $this->actingAs($user)->postJson($url, ['position' => 0, 'answer' => 'true'])->assertOk()->json();

        $this->assertFalse($first['result']['correct']);
        $this->assertSame('unknown', $again['result']['answer']);
        $this->assertSame(1, $session->fresh()->position);
        $this->actingAs($user)->postJson($url, ['position' => 5, 'answer' => 'true'])->assertStatus(409);
    }

    /** @test */
    public function a_session_of_another_student_is_hidden()
    {
        $owner = $this->student();
        $session = $this->start($owner, ['law']);
        $other = $this->student();

        $this->actingAs($other)->get(route('student.statements.session', $session))->assertNotFound();
        $this->actingAs($other)->postJson(route('student.statements.session.answer', $session), ['position' => 0, 'answer' => 'true'])
            ->assertNotFound();
    }

    /** @test */
    public function a_student_can_report_a_card_from_the_session()
    {
        $user = $this->student();
        $session = $this->start($user, ['law']);
        $url = route('student.statements.session.report', $session);

        $this->actingAs($user)->postJson($url, ['statement_id' => $session->items[0]['id'], 'message' => 'Опечатка'])->assertOk();
        $this->assertDatabaseHas('statement_reports', ['user_id' => $user->id, 'statement_id' => $session->items[0]['id']]);

        $foreign = Statement::whereNotIn('id', array_column($session->items, 'id'))->value('id');
        $this->actingAs($user)->postJson($url, ['statement_id' => $foreign])->assertStatus(422);
    }

    /** @test */
    public function the_real_base_imports_and_gives_a_session()
    {
        $data = json_decode(file_get_contents(base_path(config('statements.path'))), true);
        $plan = app(StatementImporter::class)->apply($data);
        $this->assertGreaterThan(2000, $plan['active']);
        $this->assertSame(0, app(StatementImporter::class)->plan($data)['changed']);

        $user = $this->student();
        $session = $this->start($user, array_keys(config('statements.blocks')));
        $this->assertSame(15, $session->total);
        $this->actingAs($user)->get(route('student.statements.session', $session))->assertOk();
        $last = $this->answerAll($user, $session, fn () => true);
        $this->assertTrue($last['finished']);
    }

    /** @test */
    public function reimport_hides_statements_missing_from_the_file()
    {
        app(StatementImporter::class)->apply(['statements' => [
            ['id' => 'law-1', 'block' => 'law', 'text' => 'Новое.', 'is_true' => true, 'explanation' => 'Да.', 'status' => 'active'],
        ]]);

        $this->assertSame(1, Statement::active()->count());
        $this->assertSame('removed', Statement::where('key', 'law-2')->value('status'));
    }
}
