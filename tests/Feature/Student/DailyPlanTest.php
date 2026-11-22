<?php

namespace Tests\Feature\Student;

use App\Models\DailyPlan;
use App\Models\User;
use App\Service\Trainers\DailyPlanService;
use App\Service\Trainers\Trainer;
use App\Service\Trainers\TrainerHub;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * «План на сегодня»: снимок дня, квоты, отметки «сделано», смена нормы.
 * Тренажёры — подставные (FakePlanTrainerA/B), их статус задаётся в тесте.
 */
class DailyPlanTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        FakePlanTrainer::$status = [];
        FakePlanTrainer::$sessions = [];

        config([
            'trainers.list' => [FakePlanTrainerA::class, FakePlanTrainerB::class],
            'trainers.halls' => [
                'social' => ['title' => 'Зал', 'tab' => 'Обществоведы', 'stations' => [
                    ['trainer' => FakePlanTrainerA::class, 'x' => 0, 'y' => 0],
                    ['trainer' => FakePlanTrainerB::class, 'x' => 0, 'y' => 0],
                ]],
            ],
            'fake_plan.max' => 20,
            'daily_plan.trainers' => [
                FakePlanTrainerA::class => ['review' => ['sec' => 20, 'min' => 5, 'max' => 'fake_plan.max']],
                FakePlanTrainerB::class => ['review' => ['sec' => 20, 'min' => 5, 'max' => 'fake_plan.max']],
            ],
        ]);
    }

    private function student(?int $norm = 10): User
    {
        return User::factory()->create(['role' => User::ROLE_READER, 'trainer_daily_minutes' => $norm]);
    }

    private function review(string $class, int $count): void
    {
        FakePlanTrainer::$status[$class] = ['pending' => $count, 'situation' => Trainer::REVIEW, 'count' => $count, 'next' => null, 'kind' => 'review'];
    }

    private function hub(User $user): array
    {
        // Новый запрос — новый снимок плана (сервис scoped).
        $this->app->forgetScopedInstances();

        return app(TrainerHub::class)->forUser($user);
    }

    /** @test */
    public function no_plan_until_the_student_picks_a_norm()
    {
        $this->review(FakePlanTrainerA::class, 20);

        $hub = $this->hub($this->student(null));

        $this->assertNull($hub['plan']);
        $this->assertSame(20, $hub['pending']);
        $this->assertSame(0, DailyPlan::count());
    }

    /** @test */
    public function plan_fits_the_norm_and_caps_sessions()
    {
        $user = $this->student(10);
        $this->review(FakePlanTrainerA::class, 20);
        $this->review(FakePlanTrainerB::class, 20);

        $hub = $this->hub($user);

        // 600 с на двоих по 20 с на карточку — по 15.
        $this->assertSame([15, 15], array_column($hub['plan']['items'], 'quota'));
        $this->assertSame(30, $hub['pending']);
        $this->assertSame('Повторить 15 штук', $hub['plan']['items'][0]['quest']);
        $this->assertSame(15, app(DailyPlanService::class)->quota($user, FakePlanTrainerA::class, 'review'));
    }

    /** @test */
    public function plan_does_not_grow_during_the_day()
    {
        $user = $this->student(10);
        $this->review(FakePlanTrainerA::class, 5);
        $this->hub($user);

        $this->review(FakePlanTrainerA::class, 20);
        $this->review(FakePlanTrainerB::class, 20);
        $hub = $this->hub($user);

        $this->assertSame([FakePlanTrainerA::class], array_column($hub['plan']['items'], 'key'));
        // Тренажёр вне плана для счётчиков — «на сегодня всё».
        $this->assertSame(Trainer::DONE, $hub['trainers'][1]['status']['situation']);
        $this->assertSame(0, $hub['trainers'][1]['status']['pending']);
    }

    /** @test */
    public function finished_session_marks_the_item_done_and_lifts_the_cap()
    {
        $user = $this->student(10);
        $this->review(FakePlanTrainerA::class, 20);
        $this->review(FakePlanTrainerB::class, 20);
        $this->hub($user);

        Carbon::setTestNow(now()->addMinutes(5));
        FakePlanTrainer::$sessions[FakePlanTrainerA::class] = [
            ['kind' => 'review', 'items' => 15, 'seconds' => 300, 'finished_at' => now(), 'day' => now()->toDateString()],
        ];
        $hub = $this->hub($user);

        $this->assertTrue($hub['plan']['items'][0]['done']);
        $this->assertSame(1, $hub['plan']['done']);
        $this->assertNull(app(DailyPlanService::class)->quota($user, FakePlanTrainerA::class, 'review'));

        FakePlanTrainer::$status[FakePlanTrainerB::class]['situation'] = Trainer::DONE;
        $hub = $this->hub($user);

        $this->assertTrue($hub['plan']['complete']);
        $this->assertNotNull(DailyPlan::first()->completed_at);
        Carbon::setTestNow();
    }

    /** @test */
    public function changing_the_norm_keeps_done_items_and_refills_the_rest()
    {
        $user = $this->student(10);
        $this->review(FakePlanTrainerA::class, 20);
        $this->review(FakePlanTrainerB::class, 20);
        $this->hub($user);

        Carbon::setTestNow(now()->addMinutes(5));
        FakePlanTrainer::$sessions[FakePlanTrainerA::class] = [
            ['kind' => 'review', 'items' => 15, 'seconds' => 300, 'finished_at' => now(), 'day' => now()->toDateString()],
        ];
        $this->hub($user);

        $this->app->forgetScopedInstances();
        app(DailyPlanService::class)->setNorm($user->fresh(), 25);
        $hub = $this->hub($user->fresh());

        $items = $hub['plan']['items'];
        $this->assertTrue($items[0]['done']);
        // 1500 с − 300 с сделанного: «B» получает все свои 20.
        $this->assertSame(20, $items[1]['quota']);
        $this->assertFalse($items[1]['done']);
        Carbon::setTestNow();
    }

    /** @test */
    public function the_gym_shows_the_plan()
    {
        $user = $this->student(10);
        $this->review(FakePlanTrainerA::class, 20);
        $this->review(FakePlanTrainerB::class, 20);

        $html = $this->actingAs($user)->get(route('student.trainers.index'))
            ->assertOk()
            ->assertSee('План на сегодня')
            ->assertSee('Повторить 15 штук')
            ->assertSee('0 из 2')
            ->assertSee('Норма: 10 мин в день')
            ->getContent();

        if (getenv('DAILY_PLAN_DUMP')) {
            file_put_contents(getenv('DAILY_PLAN_DUMP'), $html);
        }
    }

    /** @test */
    public function student_picks_a_norm_from_the_gym()
    {
        $user = $this->student(null);

        $this->actingAs($user)->post(route('student.trainers.norm'), ['minutes' => 15])->assertRedirect();
        $this->assertSame(15, $user->fresh()->trainer_daily_minutes);

        $this->actingAs($user)->post(route('student.trainers.norm'), ['minutes' => 7])->assertSessionHasErrors('minutes');
    }
}

abstract class FakePlanTrainer implements Trainer
{
    public static array $status = [];

    public static array $sessions = [];

    public function title(): string
    {
        return class_basename(static::class);
    }

    public function url(): string
    {
        return '/fake';
    }

    public function availableFor(User $user): bool
    {
        return isset(self::$status[static::class]);
    }

    public function statusFor(User $user): array
    {
        return self::$status[static::class];
    }

    public function planSessions(User $user, int $limit): array
    {
        return self::$sessions[static::class] ?? [];
    }

    public function words(): array
    {
        return ['what' => ['штука', 'штуки', 'штук'], 'new_what' => ['новая', 'новые', 'новых'], 'items' => 'штуки', 'new_items' => 'новые'];
    }
}

class FakePlanTrainerA extends FakePlanTrainer
{
}

class FakePlanTrainerB extends FakePlanTrainer
{
}
