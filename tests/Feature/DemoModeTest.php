<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\User;
use App\Models\UserPerson;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Демо-ученик (App\Service\Demo\DemoMode): лёгкий набор контента, сброс
 * прогресса при выходе и входе, «Наступило завтра», страница /demo.
 */
class DemoModeTest extends TestCase
{
    private function person(string $slug): Person
    {
        return Person::create([
            'slug' => $slug, 'name' => $slug, 'description' => 'd', 'association' => 'a',
            'era' => 1, 'role' => 'R', 'level' => 1, 'is_active' => true,
        ]);
    }

    private function demoUser(): User
    {
        $this->artisan('demo:create', ['--password' => 'secret123'])->assertSuccessful();

        return User::where('email', 'demo@poltavskiy')->firstOrFail();
    }

    public function test_demo_user_learns_only_the_easy_set_but_options_come_from_the_whole_base(): void
    {
        config(['demo.persons' => ['petr-i']]);
        $this->person('petr-i');
        $this->person('hard-one');
        $trainer = app(\App\Service\Persons\PersonTrainer::class);

        $real = User::factory()->create();
        Auth::login($real);
        $this->assertSame(2, $trainer->nextBatch($real)->count());

        $demo = $this->demoUser();
        Auth::login($demo);
        $this->assertSame(['petr-i'], $trainer->nextBatch($demo)->pluck('slug')->all());
        // Неверные варианты — из всей базы, не из лёгкого набора.
        $this->assertSame(2, Person::active()->count());
    }

    public function test_create_sets_the_fish_preset_and_refuses_real_accounts(): void
    {
        $demo = $this->demoUser();
        $this->assertTrue($demo->is_demo);
        $this->assertSame(105, (int) $demo->fish_total_fed);
        $this->assertSame(28, (int) $demo->fish_corm_balance);

        $real = User::factory()->create();
        $this->artisan('demo:create', ['email' => $real->email, '--password' => 'x'])->assertFailed();
        $this->assertFalse((bool) $real->fresh()->is_demo);
    }

    public function test_logout_wipes_progress_and_restores_preset(): void
    {
        $demo = $this->demoUser();
        $person = $this->person('petr-i');

        $this->post('/login', ['email' => 'demo@poltavskiy', 'password' => 'secret123'])->assertRedirect();
        UserPerson::create(['user_id' => $demo->id, 'person_id' => $person->id, 'due_on' => now(), 'learned_on' => now()]);
        $demo->fresh()->forceFill(['fish_total_fed' => 130, 'fish_corm_balance' => 0])->save();

        $this->post('/logout');

        $demo->refresh();
        $this->assertSame(0, UserPerson::where('user_id', $demo->id)->count());
        $this->assertSame(105, (int) $demo->fish_total_fed);
        $this->assertSame(28, (int) $demo->fish_corm_balance);
    }

    public function test_login_wipes_progress_left_from_a_forgotten_logout(): void
    {
        $demo = $this->demoUser();
        $person = $this->person('petr-i');
        UserPerson::create(['user_id' => $demo->id, 'person_id' => $person->id, 'due_on' => now(), 'learned_on' => now()]);

        $this->post('/login', ['email' => 'demo@poltavskiy', 'password' => 'secret123'])->assertRedirect();

        $this->assertSame(0, UserPerson::where('user_id', $demo->id)->count());
    }

    public function test_next_day_moves_reviews_to_today(): void
    {
        $demo = $this->demoUser();
        $person = $this->person('petr-i');
        UserPerson::create([
            'user_id' => $demo->id, 'person_id' => $person->id,
            'due_on' => now()->addDay()->toDateString(), 'learned_on' => now()->toDateString(),
        ]);

        $this->actingAs($demo)->post('/demo/next-day')->assertRedirect('/demo');

        $row = UserPerson::where('user_id', $demo->id)->first();
        $this->assertSame(now()->toDateString(), $row->due_on->toDateString());
        $this->assertSame(now()->subDay()->toDateString(), $row->learned_on->toDateString());
        $this->assertSame(now()->subDay()->toDateString(), $demo->fresh()->fish_last_active_date->toDateString());
    }

    public function test_demo_page_is_hidden_from_everyone_else(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))->get('/demo')->assertNotFound();
        $this->actingAs($this->demoUser())->get('/demo')->assertOk()->assertSee('Наступило завтра');
    }

    public function test_demo_user_gets_no_notifications(): void
    {
        $sent = 0;
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Notifications\Events\NotificationSent::class,
            function () use (&$sent) { $sent++; }
        );
        $demo = $this->demoUser();

        $demo->notifyNow(new class extends \Illuminate\Notifications\Notification {
            public function via($notifiable): array { return ['mail']; }
            public function toMail($notifiable) { return (new \Illuminate\Notifications\Messages\MailMessage())->line('x'); }
        });

        $this->assertSame(0, $sent);
    }
}
