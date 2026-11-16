<?php

namespace Tests\Feature\Student;

use App\Models\User;
use Tests\TestCase;

/**
 * Полосы «Сытость»/«Радость»: кормление поднимает сытость, покупка — радость
 * до 100%, низкие полосы видны на дашборде (поза wrong, своя реплика).
 */
class FishMoodTest extends TestCase
{
    private function student(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'role' => User::ROLE_READER,
            'fish_corm_balance' => 50,
        ], $attrs));
    }

    public function test_feeding_raises_satiety_from_decayed_value(): void
    {
        // Двое суток назад было 60 → сейчас 10, после кормления 20.
        $user = $this->student(['fish_satiety' => 60, 'fish_satiety_at' => now()->subDays(2)]);

        $this->actingAs($user)->post(route('student.fish.feed'), [], ['HX-Request' => 'true'])->assertOk();

        $fresh = $user->fresh();
        $this->assertEqualsWithDelta(20, $fresh->fish_satiety, 0.1);
        $this->assertEqualsWithDelta(20, app(\App\Service\FishFoodService::class)->satiety($fresh), 0.1);
    }

    public function test_purchase_fills_joy(): void
    {
        $user = $this->student(['fish_joy' => 5, 'fish_joy_at' => now()]);

        $this->actingAs($user)->post(route('student.profile.food.purchase'), ['fish_food' => 'onigiri'])
            ->assertSessionHasNoErrors();

        $this->assertSame(100.0, $user->fresh()->fish_joy);
    }

    public function test_dashboard_shows_hungry_shark(): void
    {
        $user = $this->student(['fish_satiety' => 10, 'fish_satiety_at' => now()]);

        $this->actingAs($user)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('fish-hud-row is-low', false)
            ->assertSee('data-satiety="10"', false);
    }
}
