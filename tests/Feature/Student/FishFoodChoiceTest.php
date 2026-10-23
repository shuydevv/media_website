<?php

namespace Tests\Feature\Student;

use App\Models\User;
use Tests\TestCase;

/**
 * Выбор еды для акулы на вкладке «Персонаж»: бесплатная выбирается сразу,
 * платная — только после покупки за корм; выбранная становится иконкой
 * корма. База — SQLite в памяти (см. phpunit.xml).
 */
class FishFoodChoiceTest extends TestCase
{
    private function student(int $balance = 0, int $totalFed = 0): User
    {
        return User::factory()->create([
            'role' => User::ROLE_READER,
            'fish_corm_balance' => $balance,
            'fish_total_fed' => $totalFed,
        ]);
    }

    /** fish_total_fed, с которого акула на 3 уровне (config('fish.levels')). */
    private function level3Fed(): int
    {
        return config('fish.levels')[1];
    }

    public function test_profile_shows_food_choice_with_default_burger_icon(): void
    {
        $this->actingAs($this->student())
            ->get(route('student.profile.show'))
            ->assertOk()
            ->assertSee('img/food/burger.svg', false)
            ->assertSee('Онигири');
    }

    public function test_free_food_can_be_selected(): void
    {
        $user = $this->student();

        $this->actingAs($user)->post(route('student.profile.food.select'), ['fish_food' => 'pizza'])
            ->assertSessionHasNoErrors();

        $this->assertSame('pizza', $user->fresh()->fish_food);
        $this->assertStringEndsWith('img/food/pizza.svg', $user->fresh()->fish_food_icon_url);
    }

    public function test_paid_food_cannot_be_selected_before_purchase(): void
    {
        $user = $this->student(100);

        $this->actingAs($user)->post(route('student.profile.food.select'), ['fish_food' => 'onigiri'])
            ->assertSessionHasErrors('fish_food');

        $this->assertNull($user->fresh()->fish_food);
    }

    public function test_purchase_deducts_corm_and_selects_food(): void
    {
        $user = $this->student(12, $this->level3Fed());

        $this->actingAs($user)->post(route('student.profile.food.purchase'), ['fish_food' => 'snus'])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame(2, (int) $user->fish_corm_balance);
        $this->assertSame('snus', $user->fish_food);
        $this->assertSame(['snus'], $user->fish_unlocked_foods);
        $this->assertStringEndsWith('img/food/snus_mini.svg', $user->fish_food_crumb_url);
    }

    public function test_purchase_with_insufficient_corm_fails(): void
    {
        $user = $this->student(3, $this->level3Fed());

        $this->actingAs($user)->post(route('student.profile.food.purchase'), ['fish_food' => 'energy'])
            ->assertSessionHasErrors('fish_food');

        $this->assertSame(3, (int) $user->fresh()->fish_corm_balance);
        $this->assertNull($user->fresh()->fish_food);
    }

    public function test_adult_food_needs_level_3(): void
    {
        $user = $this->student(100, $this->level3Fed() - 1);

        $this->actingAs($user)->post(route('student.profile.food.purchase'), ['fish_food' => 'cigarette'])
            ->assertSessionHasErrors('fish_food');

        $this->assertSame(100, (int) $user->fresh()->fish_corm_balance);
        $this->assertNull($user->fresh()->fish_unlocked_foods);

        // Онигири уровня не требует — покупается на любом.
        $this->actingAs($user)->post(route('student.profile.food.purchase'), ['fish_food' => 'onigiri'])
            ->assertSessionHasNoErrors();
        $this->assertSame(95, (int) $user->fresh()->fish_corm_balance);
    }

    public function test_profile_shows_level_requirement(): void
    {
        $this->actingAs($this->student(100))
            ->get(route('student.profile.show'))
            ->assertOk()
            ->assertSee('с 3 уровня');
    }
}
