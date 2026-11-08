<?php

namespace Tests\Feature\Student;

use App\Models\Person;
use App\Models\User;
use App\Models\UserPerson;
use App\Service\Persons\PersonRanks;
use Tests\TestCase;

/**
 * Звания Адмирала Клешни: пороги «героев в строю» (все выученные), вручение один
 * раз, перескок звания, звания не отбираются.
 */
class PersonRanksTest extends TestCase
{
    private PersonRanks $ranks;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ranks = app(PersonRanks::class);

        // База побольше: высшее звание — «все личности базы», и маленькая
        // выборка ученика не должна его давать.
        Person::insert(array_map(fn ($i) => [
            'slug' => "base-{$i}", 'name' => "База {$i}", 'description' => 'кто', 'association' => 'что',
            'era' => 1, 'role' => 'R', 'level' => 1, 'sort_order' => 1000 + $i, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ], range(1, 100)));
    }

    private function studentWithMore(User $user, int $count): void
    {
        foreach (range(1, $count) as $i) {
            $person = Person::create([
                'slug' => "more{$user->id}-{$i}", 'name' => "Ещё {$i}", 'description' => 'кто', 'association' => 'что',
                'era' => 1, 'role' => 'R', 'level' => 1, 'sort_order' => 500 + $i,
            ]);
            UserPerson::create(['user_id' => $user->id, 'person_id' => $person->id, 'step' => 1, 'due_on' => today(), 'learned_on' => today()]);
        }
    }

    /** Ученик, у которого $steps — шаги сетки выученных личностей. */
    private function studentWith(array $steps): User
    {
        $user = User::factory()->create(['role' => User::ROLE_READER]);
        foreach ($steps as $i => $step) {
            $person = Person::create([
                'slug' => "p{$user->id}-{$i}", 'name' => "Личность {$i}", 'description' => 'кто', 'association' => 'что',
                'era' => 1, 'role' => 'R', 'level' => 1, 'sort_order' => $i,
            ]);
            UserPerson::create([
                'user_id' => $user->id, 'person_id' => $person->id, 'step' => $step,
                'due_on' => today(), 'learned_on' => today(),
            ]);
        }

        return $user;
    }

    /** @test */
    public function a_cabin_boy_on_the_first_visit_and_a_sailor_at_fifteen()
    {
        $user = $this->studentWith(array_fill(0, 14, 1));

        $this->assertSame('Юнга', $this->ranks->introduce($user)['title']);
        $this->assertNull($this->ranks->introduce($user));
        $this->assertNull($this->ranks->promote($user));

        $this->studentWithMore($user, 1);
        $rank = $this->ranks->promote($user);
        $this->assertSame('Матрос', $rank['title']);
        $this->assertFalse($rank['skipped']);
        $this->assertNull($this->ranks->promote($user));
    }

    /** @test */
    public function every_learned_person_counts_and_a_skipped_rank_is_marked()
    {
        // Выученные сегодня (шаг 1) считаются наравне с повторёнными.
        $user = $this->studentWith(array_merge(array_fill(0, 10, 2), array_fill(0, 20, 1)));
        $this->assertSame(30, $this->ranks->inLine($user));

        $this->ranks->introduce($user);
        $rank = $this->ranks->promote($user);
        $this->assertSame('Старший матрос', $rank['title']);
        $this->assertTrue($rank['skipped']);

        $summary = $this->ranks->summary($user);
        $this->assertSame('Старшина', $summary['next']['title']);
        $this->assertSame(20, $summary['left']);
    }

    /** @test */
    public function a_rank_is_never_taken_back()
    {
        $user = $this->studentWith(array_fill(0, 15, 2));
        $this->ranks->promote($user);
        UserPerson::where('user_id', $user->id)->limit(10)->delete();

        $this->assertNull($this->ranks->promote($user->fresh()));
        $this->assertSame(2, (int) $user->fresh()->persons_rank);
        $this->assertSame('матрос', $this->ranks->address($user->fresh()));
    }
}
