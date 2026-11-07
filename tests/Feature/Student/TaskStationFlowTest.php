<?php

namespace Tests\Feature\Student;

use App\Models\Category;
use App\Models\Task;
use App\Models\TaskReview;
use App\Models\TaskRound;
use App\Models\User;
use App\Models\UserTask;
use App\Service\Bank\BankImporter;
use App\Service\Bank\WrittenPractice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Станция «Задания»: раунд первой части до итога, баллы по ЕГЭ, повтор
 * ошибок, корм (полные раунды и сверхурочные); вторая часть — черновик,
 * самопроверка без корма, отправка куратору с лимитом, проверка
 * куратором и корм = балл.
 */
class TaskStationFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-07 12:00:00');

        $category = Category::create(['title' => 'Обществознание']);
        $rows = [];
        $ext = 1;
        // Первая часть: номера 2 (2 балла) и 3 (1 балл), по 15 заданий.
        foreach ([2, 3] as $number) {
            for ($i = 1; $i <= 15; $i++) {
                $rows[] = [
                    'ext' => $ext++, 'number' => $number, 'block' => $i % 2 ? 'law' : 'economy',
                    'theme' => 'тема '.($i % 4), 'type' => 'test',
                    'question' => "Задание {$number}.{$i}", 'options' => ['а', 'б', 'в', 'г', 'д'],
                    'answer' => '135', 'explanation' => 'Разбор.', 'status' => 'active', 'also' => [],
                ];
            }
        }
        // Вторая часть: 19 — три задания, 17+18 — пара с общим текстом.
        for ($i = 1; $i <= 3; $i++) {
            $rows[] = ['ext' => $ext++, 'number' => 19, 'block' => 'law', 'type' => 'written',
                'question' => "Приведите три примера {$i}.", 'answer' => 'Образец 19.', 'status' => 'active', 'also' => []];
        }
        foreach ([17, 18] as $n) {
            $rows[] = ['ext' => $ext++, 'number' => $n, 'block' => 'politics', 'type' => 'written', 'group' => 'g1',
                'passage' => 'Общий текст.', 'question' => "Вопросы {$n}.", 'answer' => "Образец {$n}.", 'status' => 'active', 'also' => []];
        }
        $rows[] = ['ext' => $ext++, 'number' => 2, 'block' => 'law', 'type' => 'test', 'question' => 'Скрытое',
            'answer' => '1', 'status' => 'hidden', 'also' => []];

        app(BankImporter::class)->apply(['max_by_number' => [2 => 2, 3 => 1, 17 => 2, 18 => 2, 19 => 3], 'tasks' => $rows], $category);
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

    private function mentor(): User
    {
        return User::factory()->create(['role' => User::ROLE_MENTOR]);
    }

    private function startRound(User $user, array $numbers, array $blocks = []): TaskRound
    {
        $this->actingAs($user)->post(route('student.ege.start'), ['numbers' => $numbers, 'blocks' => $blocks])->assertRedirect();

        return TaskRound::where('user_id', $user->id)->latest('id')->firstOrFail();
    }

    /** $answer(position) — что ответить. */
    private function answerAll(User $user, TaskRound $round, callable $answer): array
    {
        $last = null;
        foreach ($round->items as $position => $id) {
            $url = route('student.ege.round.answer', $round);
            $last = $this->actingAs($user)->postJson($url, ['position' => $position, 'answer' => $answer($position)])->assertOk()->json();
            // Не на полный балл — сразу смотрим разбор (без второй попытки).
            if ($last['pending'] ?? false) {
                $last = $this->actingAs($user)->postJson($url, ['position' => $position, 'mode' => 'reveal'])->assertOk()->json();
            }
        }

        return $last;
    }

    public function test_without_access_the_station_is_closed(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_READER]);
        $this->actingAs($user)->get(route('student.ege.index'))->assertForbidden();
    }

    public function test_pages_render(): void
    {
        $user = $this->student();
        $this->actingAs($user)->get(route('student.ege.index'))->assertOk()
            ->assertSee('Какие номера качаем?')->assertSee('Суждения')->assertSee('Решено: 0');
        $this->actingAs($user)->get(route('student.ege.index', ['part' => 2]))->assertOk()
            ->assertSee('Какой номер решаем?')->assertSee('Примеры');

        $round = $this->startRound($user, [2]);
        $this->actingAs($user)->get(route('student.ege.round', $round))->assertOk()->assertSee('Задание 2.', false);
        $this->answerAll($user, $round, fn () => '135');
        // Итог открывается и после перезагрузки.
        $this->actingAs($user)->get(route('student.ege.round', $round))->assertOk()->assertSee('summary', false);

        // Отправленный ответ — в очереди куратора.
        $this->actingAs($user)->post(route('student.ege.written.start'), ['number' => 19]);
        $review = TaskReview::where('user_id', $user->id)->firstOrFail();
        $this->actingAs($user)->post(route('student.ege.written.send', $review), ['answers' => [$review->items[0] => str_repeat('Пример. ', 10)]]);
        $this->actingAs($this->mentor())->get(route('mentor.submissions.index'))->assertOk()->assertSee('Задания из тренажёра');
        $this->actingAs($user)->get(route('student.ege.index', ['part' => 2]))->assertOk()->assertSee('ждёт проверки');
    }

    public function test_round_takes_only_chosen_numbers_and_blocks(): void
    {
        $user = $this->student();

        // Только номер — все блоки этого номера.
        $round = $this->startRound($user, [2]);
        $tasks = Task::whereIn('id', $round->items)->get();
        $this->assertSame(['2'], $tasks->pluck('number')->unique()->values()->all());
        $this->assertNotContains('Скрытое', $tasks->pluck('question_text')->all());

        // Блок — вместо номеров: все номера первой части по праву
        // (номер, если пришёл вместе с блоком, не сужает выбор).
        $round = $this->startRound($user, [2], ['law']);
        $this->assertSame(10, $round->total); // 8 + 8 заданий по праву в № 2 и 3, раунд — 10
        $tasks = Task::whereIn('id', $round->items)->get();
        $this->assertSame(['law'], $tasks->pluck('block')->unique()->values()->all());
        $this->assertEqualsCanonicalizing(['2', '3'], $tasks->pluck('number')->unique()->values()->all());

        $this->actingAs($user)->post(route('student.ege.start'), ['blocks' => ['law']])->assertRedirect();
        $this->actingAs($user)->post(route('student.ege.start'), [])->assertSessionHasErrors('numbers');
    }

    public function test_full_round_scores_by_ege_rules_and_pays_corm(): void
    {
        $user = $this->student();
        $round = $this->startRound($user, [2, 3]);
        $this->assertSame(10, $round->total);

        // Первые два — с одной ошибкой (за № 2 — 1 из 2, за № 3 — 0 из 1), остальные верно.
        $last = $this->answerAll($user, $round, fn ($p) => $p < 2 ? '13' : '531');

        $this->assertTrue($last['finished']);
        $round->refresh();
        $this->assertSame(TaskRound::FINISHED, $round->status);
        $this->assertSame($round->max_score, array_sum(array_column($round->results, 'max')));
        $this->assertLessThan($round->max_score, $round->score);
        $this->assertSame(10, DB::table('task_attempts')->where('round_id', $round->id)->count());

        // Ошибки — на повтор завтра.
        $this->assertSame(2, UserTask::where('user_id', $user->id)->whereDate('due_on', '2026-11-08')->count());
        $this->assertGreaterThan(0, $round->corm_awarded);
        $this->assertSame($round->corm_awarded, $user->fresh()->fish_corm_balance);
    }

    public function test_answer_replay_and_stale_position(): void
    {
        $user = $this->student();
        $round = $this->startRound($user, [3]);
        $url = route('student.ege.round.answer', $round);

        $first = $this->actingAs($user)->postJson($url, ['position' => 0, 'answer' => '135'])->assertOk()->json();
        $again = $this->actingAs($user)->postJson($url, ['position' => 0, 'answer' => '2'])->assertOk()->json();
        $this->assertSame($first['result']['score'], $again['result']['score']);
        $this->actingAs($user)->postJson($url, ['position' => 3, 'answer' => '1'])->assertStatus(409);
    }

    public function test_second_try_hides_answer_and_costs_a_point(): void
    {
        $user = $this->student();
        $round = $this->startRound($user, [2]);
        $url = route('student.ege.round.answer', $round);

        // Первая попытка мимо — верный ответ и разбор не отдаём, позиция та же.
        $first = $this->actingAs($user)->postJson($url, ['position' => 0, 'answer' => '24'])->assertOk()->json();
        $this->assertTrue($first['pending']);
        $this->assertArrayNotHasKey('correct', $first['result']);
        $this->assertSame(1, $first['result']['retry_max']);
        $this->assertSame(0, $round->fresh()->position);

        // Перезагрузка страницы: выбор на месте, верного ответа и его длины в данных нет.
        $trainer = app(\App\Service\Bank\TaskTrainer::class);
        $this->assertTrue($trainer->pendingNow($round->fresh())['pending']);
        $this->assertArrayNotHasKey('length', $trainer->cards($round->fresh())[0]);

        // Вторая — верно: «Верно», но 1 из 2, и задание уходит в ошибки.
        $second = $this->actingAs($user)->postJson($url, ['position' => 0, 'answer' => '135', 'mode' => 'retry'])->assertOk()->json();
        $this->assertSame('right', $second['result']['status']);
        $this->assertTrue($second['result']['second']);
        $this->assertSame(1, $second['result']['score']);
        $this->assertSame(1, $round->fresh()->position);
        $this->assertSame(2, DB::table('task_attempts')->where('round_id', $round->id)->count());
        $this->assertFalse((bool) UserTask::where('user_id', $user->id)->where('task_id', $round->items[0])->value('last_correct'));

        // «Посмотреть разбор» — засчитана первая попытка (частично верно).
        $this->actingAs($user)->postJson($url, ['position' => 1, 'answer' => '13'])->assertOk()->assertJsonPath('pending', true);
        $reveal = $this->actingAs($user)->postJson($url, ['position' => 1, 'mode' => 'reveal'])->assertOk()->json();
        $this->assertSame('partial', $reveal['result']['status']);
        $this->assertSame('135', $reveal['result']['correct']);
        $this->assertSame(1, $reveal['result']['score']);

        // Повтор/перерешивание после закрытия ничего не меняет.
        $this->actingAs($user)->postJson($url, ['position' => 1, 'answer' => '135', 'mode' => 'retry'])->assertOk()->assertJsonPath('result.score', 1);
        $this->assertSame(2, $round->fresh()->score);
    }

    public function test_mistakes_round_gives_no_corm(): void
    {
        $user = $this->student();
        $round = $this->startRound($user, [3]);
        $this->answerAll($user, $round, fn ($p) => $p < 3 ? '1' : '135');
        $balance = $user->fresh()->fish_corm_balance;

        $this->actingAs($user)->post(route('student.ege.mistakes', $round))->assertRedirect();
        $repeat = TaskRound::where('kind', TaskRound::KIND_MISTAKES)->firstOrFail();
        $this->assertSame(3, $repeat->total);
        $this->answerAll($user, $repeat, fn () => '135');
        $this->assertSame(0, $repeat->fresh()->corm_awarded);
        $this->assertSame($balance, $user->fresh()->fish_corm_balance);
    }

    public function test_after_full_rounds_only_overtime_corm(): void
    {
        config(['task_trainer.corm_full_rounds' => 1]);
        $user = $this->student();

        $first = $this->startRound($user, [2, 3]);
        $this->answerAll($user, $first, fn () => '135');
        $this->assertSame(3, $first->fresh()->corm_awarded);

        $second = $this->startRound($user, [2, 3]);
        $this->answerAll($user, $second, fn () => '135');
        $this->assertSame(1, $second->fresh()->corm_awarded);

        $third = $this->startRound($user, [2, 3]);
        $this->answerAll($user, $third, fn () => '9');
        $this->assertSame(0, $third->fresh()->corm_awarded);
        $this->assertSame(4, $user->fresh()->fish_corm_balance);
    }

    public function test_self_check_gives_no_corm_and_locks_sending(): void
    {
        $user = $this->student();
        $this->actingAs($user)->post(route('student.ege.written.start'), ['number' => 19])->assertRedirect();
        $review = TaskReview::where('user_id', $user->id)->firstOrFail();
        $taskId = $review->items[0];

        $this->actingAs($user)->get(route('student.ege.written', $review))->assertOk()->assertDontSee('Образец 19.');

        $this->actingAs($user)->postJson(route('student.ege.written.draft', $review), ['answers' => [$taskId => 'Черновик']])->assertOk();
        $this->assertSame('Черновик', $review->fresh()->answers[(string) $taskId]);

        $this->actingAs($user)->post(route('student.ege.written.self', $review), ['answers' => [$taskId => 'Мой ответ']])->assertRedirect();
        $this->assertSame(TaskReview::SELF, $review->fresh()->status);
        $this->actingAs($user)->get(route('student.ege.written', $review))->assertOk()->assertSee('Образец 19.');

        $marks = [$taskId => ['items' => [1, 1, 0], 'penalty' => 0]];
        $this->actingAs($user)->postJson(route('student.ege.written.marks', $review), ['marks' => $marks])
            ->assertOk()->assertJson(['self_score' => 2, 'max' => 3]);

        $this->actingAs($user)->post(route('student.ege.written.send', $review), ['answers' => [$taskId => str_repeat('а', 60)]]);
        $this->assertSame(TaskReview::SELF, $review->fresh()->status);
        $this->assertSame(0, $user->fresh()->fish_corm_balance);
    }

    public function test_pair_17_18_is_one_approach(): void
    {
        $user = $this->student();
        $this->actingAs($user)->post(route('student.ege.written.start'), ['number' => 18])->assertRedirect();
        $review = TaskReview::where('user_id', $user->id)->firstOrFail();

        $this->assertCount(2, $review->items);
        $this->assertSame(['17', '18'], $review->tasks()->pluck('number')->all());
        $this->assertSame(4, $review->max_score);
    }

    public function test_send_to_curator_limit_and_grading_pays_corm(): void
    {
        config(['task_trainer.reviews_per_day' => 1]);
        $user = $this->student();
        $answer = str_repeat('Пример с подробностями. ', 4);

        $this->actingAs($user)->post(route('student.ege.written.start'), ['number' => 19]);
        $review = TaskReview::where('user_id', $user->id)->latest('id')->firstOrFail();
        $taskId = $review->items[0];

        // Слишком короткий — не отправляется.
        $this->actingAs($user)->post(route('student.ege.written.send', $review), ['answers' => [$taskId => 'коротко']])
            ->assertSessionHasErrors('answers');
        $this->assertSame(TaskReview::DRAFT, $review->fresh()->status);

        $this->actingAs($user)->post(route('student.ege.written.send', $review), ['answers' => [$taskId => $answer]])->assertRedirect();
        $this->assertSame(TaskReview::SENT, $review->fresh()->status);

        // Лимит: второй ответ за день не уходит.
        $this->actingAs($user)->post(route('student.ege.written.start'), ['number' => 19]);
        $second = TaskReview::where('user_id', $user->id)->latest('id')->firstOrFail();
        $this->actingAs($user)->post(route('student.ege.written.send', $second), ['answers' => [$second->items[0] => $answer]])
            ->assertSessionHasErrors('answers');

        // Куратор видит в очереди, ставит 2 из 3 — корм 2; поправил на 3 — корм 3.
        $mentor = $this->mentor();
        $this->actingAs($mentor)->get(route('mentor.task-reviews.show', $review))->assertOk()->assertSee('Образец 19.');
        $this->actingAs($mentor)->put(route('mentor.task-reviews.update', $review), ['scores' => [$taskId => 2], 'comment' => 'Третий пример общий.'])
            ->assertRedirect();
        $review->refresh();
        $this->assertSame(TaskReview::CHECKED, $review->status);
        $this->assertSame(2, $review->score);
        $this->assertSame(2, $user->fresh()->fish_corm_balance);

        $this->actingAs($mentor)->put(route('mentor.task-reviews.update', $review), ['scores' => [$taskId => 3]]);
        $this->assertSame(3, $user->fresh()->fish_corm_balance);

        // Ученик видит оценку и комментарий.
        $this->actingAs($user)->get(route('student.ege.written', $review))->assertOk()->assertSee('3 из 3');
        $this->assertNotNull($review->fresh()->seen_at);
    }

    public function test_self_check_scoring_rules(): void
    {
        // 19: два примера, один лишний с ошибкой — минус балл; два лишних — 0.
        $this->assertSame(2, WrittenPractice::scoreFor(19, [1, 1, 0], 0, 3));
        $this->assertSame(1, WrittenPractice::scoreFor(19, [1, 1, 0], 1, 3));
        $this->assertSame(0, WrittenPractice::scoreFor(19, [1, 1, 1], 2, 3));
        // 17: три ответа — 2, два — 1, один — 0.
        $this->assertSame(2, WrittenPractice::scoreFor(17, [1, 1, 1], 0, 2));
        $this->assertSame(1, WrittenPractice::scoreFor(17, [1, 0, 1], 0, 2));
        $this->assertSame(0, WrittenPractice::scoreFor(17, [1, 0, 0], 0, 2));
        // 24: 24.2 — только при 24.1 = 3.
        $this->assertSame(4, WrittenPractice::scoreFor(24, [3, 1], 0, 4));
        $this->assertSame(2, WrittenPractice::scoreFor(24, [2, 1], 0, 4));
        // 25: сумма критериев.
        $this->assertSame(5, WrittenPractice::scoreFor(25, [2, 0, 3], 0, 6));
    }
}
