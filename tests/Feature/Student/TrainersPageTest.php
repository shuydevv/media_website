<?php

namespace Tests\Feature\Student;

use App\Models\TermSession;
use App\Models\User;
use App\Service\Dates\HistImporter;
use App\Service\Terms\TermImporter;
use App\Service\Trainers\TrainerHub;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Общая страница тренажёров и общий скрипт сессии — то, что тренажёр дат
 * делит с тренажёром терминов.
 */
class TrainersPageTest extends TestCase
{
    private function studentWith(array $tables): User
    {
        $user = User::factory()->create(['role' => User::ROLE_READER]);
        foreach ($tables as $table) {
            DB::table($table)->insert(['user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        }

        return $user;
    }

    private function importBoth(): void
    {
        app(TermImporter::class)->apply(json_decode(file_get_contents(base_path('docs/terms/egkh_terms_18.json')), true));
        app(HistImporter::class)->apply(json_decode(file_get_contents(base_path('docs/history/events_base.json')), true));
    }

    /** @test */
    public function with_two_trainers_the_button_leads_to_the_common_page()
    {
        $this->importBoth();
        $user = $this->studentWith(['term_trainer_users', 'hist_trainer_users']);

        // Доступ к обществознанию открывает термины, «Верные суждения» и «Задания»,
        // к истории — даты и личности.
        $hub = app(TrainerHub::class)->forUser($user);
        $this->assertCount(5, $hub['trainers']);
        $this->assertSame(route('student.trainers.index'), $hub['url']);

        $this->actingAs($user)->get(route('student.trainers.index'))
            ->assertOk()->assertSee('Термины')->assertSee('Даты')->assertSee('Верные суждения');
    }

    /** @test */
    public function with_one_trainer_the_gym_shows_it_and_locks_the_other_hall()
    {
        $this->importBoth();
        $user = $this->studentWith(['term_trainer_users']);

        $this->assertSame(route('student.trainers.index'), app(TrainerHub::class)->forUser($user)['url']);
        $this->actingAs($user)->get(route('student.trainers.index'))
            ->assertOk()
            ->assertSee(route('student.terms.index'))
            ->assertDontSee(route('student.dates.index'))
            ->assertSee('Зал историков закрыт');
    }

    /** @test */
    public function the_soon_stations_show_only_when_switched_on()
    {
        $this->importBoth();
        $user = $this->studentWith(['term_trainer_users']);

        config(['trainers.show_soon' => false]);
        $this->actingAs($user)->get(route('student.trainers.index'))
            ->assertOk()->assertDontSee('<span class="gym-soon">скоро</span>', false);

        config(['trainers.show_soon' => true]);
        $this->actingAs($user)->get(route('student.trainers.index'))
            ->assertOk()->assertSee('<span class="gym-soon">скоро</span>', false);
    }

    /** @test */
    public function the_gym_opens_the_requested_hall()
    {
        $this->importBoth();
        $user = $this->studentWith(['term_trainer_users', 'hist_trainer_users']);

        $this->actingAs($user)->get(route('student.trainers.index', ['hall' => 'history']))
            ->assertOk()
            ->assertSee('data-hall-tab="history" aria-selected="true"', false);
    }

    /** @test */
    public function without_trainers_there_is_no_button_but_the_gym_still_opens()
    {
        $user = $this->studentWith([]);

        $this->assertNull(app(TrainerHub::class)->forUser($user)['url']);
        $this->actingAs($user)->get(route('student.trainers.index'))
            ->assertOk()
            ->assertSee('Пока упражнений нет');
    }

    /** @test */
    public function the_terms_session_still_has_its_script()
    {
        $this->importBoth();
        $user = $this->studentWith(['term_trainer_users']);

        $this->actingAs($user)->post(route('student.terms.start', 'learn'));
        $session = TermSession::where('user_id', $user->id)->firstOrFail();

        $this->actingAs($user)->get(route('student.terms.session', $session))
            ->assertOk()
            ->assertSee('BURGER_SRC', false)
            ->assertSee('id="terms-app"', false);
    }
}
