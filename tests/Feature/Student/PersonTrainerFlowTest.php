<?php

namespace Tests\Feature\Student;

use App\Models\Person;
use App\Models\PersonSession;
use App\Models\User;
use App\Models\UserPerson;
use App\Service\Persons\PersonImporter;
use App\Service\Persons\PersonTrainer;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Тренажёр личностей целиком на небольшой выборке из настоящей базы
 * (24 личности, без портретов): заливка, порция новых через HTTP, повторение
 * с соответствием «как в ЕГЭ», ввод фамилии со старшего шага, доступ.
 */
class PersonTrainerFlowTest extends TestCase
{
    private array $data;

    protected function setUp(): void
    {
        parent::setUp();
        $full = json_decode(file_get_contents(base_path('docs/personalities/personalities.json')), true);

        // Первые пять по порядку изучения и ещё 19 через весь список (чтобы
        // были не только правители); связи — только внутри выборки.
        $picked = array_slice($full['order'], 0, 5);
        for ($i = 5; count($picked) < 24; $i += 17) {
            $picked[] = $full['order'][$i];
        }
        $keep = array_flip($picked);
        $people = array_values(array_filter($full['people'], fn ($p) => isset($keep[$p['id']])));
        foreach ($people as &$p) {
            $p['image'] = null;
            if ($p['related']) {
                $p['related']['ids'] = array_values(array_filter($p['related']['ids'], fn ($id) => isset($keep[$id])));
            }
        }
        unset($p);

        $this->data = ['eras' => $full['eras'], 'people' => $people, 'order' => array_keys($keep)];
        $this->assertSame([], PersonImporter::validate($this->data));
        app(PersonImporter::class)->apply($this->data);
    }

    private function student(): User
    {
        $user = User::factory()->create(['role' => User::ROLE_READER]);
        DB::table('hist_trainer_users')->insert(['user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    /** Отвечает на текущий шаг верно — так, как это сделала бы страница. */
    private function answerRight(User $user, PersonSession $session): void
    {
        $session->refresh();
        $current = $session->state['current'];

        match ($current['type']) {
            'card' => $this->actingAs($user)->post(route('student.persons.session.card', $session), ['remembered' => 1]),
            'a' => $this->actingAs($user)->post(route('student.persons.session.answer', $session), [
                'typed' => Person::find($current['person_id'])->answer,
            ]),
            'b', 'c' => $this->actingAs($user)->post(route('student.persons.session.choose', $session), [
                'choice' => array_search($current['person_id'], $current['options'], true),
            ]),
            'd' => $this->actingAs($user)->post(route('student.persons.session.match', $session), [
                'answer' => implode('', array_map(fn ($id) => array_search($id, $current['options'], true) + 1, $current['rows'])),
            ]),
        };

        $session->refresh();
        $current = $session->state['current'];
        if ($current === null) {
            return;
        }
        $answered = match ($current['type']) {
            'a', 'd' => $current['result'] !== null,
            'b', 'c' => $current['done'],
            default => false,
        };
        if ($answered) {
            $this->actingAs($user)->post(route('student.persons.session.next', $session));
        }
    }

    private function play(User $user, PersonSession $session, int $limit = 60): array
    {
        $types = [];
        $steps = 0;
        while (!$session->refresh()->finished_at && $steps++ < $limit) {
            // Каждый шаг страница рисует без ошибок.
            $this->actingAs($user)->get(route('student.persons.session', $session))->assertOk();
            $types[] = $session->refresh()->state['current']['type'] ?? null;
            $this->answerRight($user, $session);
        }

        return $types;
    }

    /** @test */
    public function reimport_changes_nothing()
    {
        $this->assertSame(24, Person::count());
        $plan = app(PersonImporter::class)->plan($this->data);

        $this->assertSame([], $plan['added']);
        $this->assertSame([], $plan['changed']);
        $this->assertSame([], $plan['removed']);
    }

    /** @test */
    public function a_student_learns_a_batch_and_reviews_it_with_an_exam_style_matching()
    {
        $user = $this->student();

        // Первый вход — знакомство с Адмиралом Клешней, один раз.
        $this->actingAs($user)->get(route('student.persons.index'))->assertOk()->assertSee('Тренажёр личностей')
            ->assertSee('Прибытие на службу')->assertSee('Юнга');
        $this->actingAs($user)->get(route('student.persons.index'))->assertOk()->assertDontSee('id="prs-rank"', false);

        $this->actingAs($user)->post(route('student.persons.start', 'learn'))->assertRedirect();
        $session = PersonSession::where('user_id', $user->id)->where('kind', 'learn')->firstOrFail();
        $this->assertSame(
            array_slice($this->data['order'], 0, 5),
            Person::whereIn('id', $session->state['terms'])->orderBy('sort_order')->pluck('slug')->all()
        );

        $types = $this->play($user, $session);
        $this->assertEqualsCanonicalizing(['card', 'b', 'c'], array_values(array_unique($types)));

        $session->refresh();
        $this->assertSame(10, (int) $session->correct_first);
        $this->assertSame(3, (int) $session->corm_awarded);
        $this->assertSame(5, UserPerson::where('user_id', $user->id)->where('next_method', 'b')->count());
        // Пять выученных — до матроса (15) ещё далеко: окна нет, звание прежнее.
        $this->actingAs($user)->get(route('student.persons.session', $session))->assertOk()->assertSee('с первой попытки')
            ->assertDontSee('id="prs-rank"', false);
        $this->assertSame(1, (int) $user->fresh()->persons_rank);

        // Назавтра — повторение: «Чем известен?» и в конце соответствие.
        $this->artisan('persons:shift', ['user' => $user->id])->assertSuccessful();
        $this->actingAs($user)->post(route('student.persons.start', 'review'));
        $review = PersonSession::where('user_id', $user->id)->where('kind', 'review')->firstOrFail();
        $this->assertSame(5, (int) $review->total);

        $types = $this->play($user, $review);
        $this->assertSame(['b', 'b', 'b', 'b', 'b', 'd'], $types);

        $review->refresh();
        $this->assertSame([['ok' => 4, 'of' => 4]], $review->state['match']);
        $this->assertSame(5, UserPerson::where('user_id', $user->id)->where('step', 2)->where('next_method', 'c')->count());
        $this->actingAs($user)->get(route('student.persons.session', $review))->assertOk()->assertSee('Как в ЕГЭ: 4 из 4');
    }

    /** @test */
    public function a_wrong_choice_shows_whose_answer_it_was_and_comes_back()
    {
        $user = $this->student();
        $this->actingAs($user)->post(route('student.persons.start', 'learn'));
        $session = PersonSession::where('user_id', $user->id)->firstOrFail();
        $this->actingAs($user)->get(route('student.persons.session', $session));
        $guard = 0;
        while ($session->refresh()->state['current']['type'] === 'card' && $guard++ < 10) {
            $this->actingAs($user)->post(route('student.persons.session.card', $session), ['remembered' => 1]);
        }

        $current = $session->refresh()->state['current'];
        $this->assertSame('b', $current['type']);
        $this->assertCount(4, $current['options']);
        $wrong = collect($current['options'])->search(fn ($id) => $id !== $current['person_id']);
        $owner = Person::find($current['options'][$wrong]);

        $this->actingAs($user)
            ->post(route('student.persons.session.choose', $session), ['choice' => $wrong], ['HX-Request' => 'true'])
            ->assertOk()
            ->assertSee($owner->name);

        $this->actingAs($user)->post(route('student.persons.session.next', $session));
        $queue = $session->refresh()->state['queue'];
        $this->assertSame(['t' => $current['person_id'], 's' => 'b'], end($queue));
    }

    /** @test */
    public function the_surname_is_typed_from_the_seven_day_step()
    {
        $user = $this->student();
        $trainer = app(PersonTrainer::class);
        $typable = Person::all()->filter(fn ($p) => $trainer->canType($p))->take(2)->values();
        $this->assertCount(2, $typable);

        foreach ($typable as $person) {
            UserPerson::create([
                'user_id' => $user->id, 'person_id' => $person->id, 'step' => 3,
                'due_on' => now()->toDateString(), 'learned_on' => now()->subDays(10)->toDateString(),
                'next_method' => 'a',
            ]);
        }

        $this->actingAs($user)->post(route('student.persons.start', 'review'));
        $review = PersonSession::where('user_id', $user->id)->where('kind', 'review')->firstOrFail();
        $this->actingAs($user)->get(route('student.persons.session', $review))->assertOk()->assertSee('Открыть букву');

        $types = $this->play($user, $review);
        $this->assertSame(['a', 'a'], $types);
        $this->assertSame(2, UserPerson::where('user_id', $user->id)->where('step', 4)->where('next_method', 'b')->count());
    }

    /** @test */
    public function free_training_has_a_matching_after_every_five_people()
    {
        $user = $this->student();
        foreach (Person::orderBy('sort_order')->take(7)->get() as $person) {
            UserPerson::create([
                'user_id' => $user->id, 'person_id' => $person->id, 'step' => 2,
                'due_on' => now()->addDays(3)->toDateString(), 'learned_on' => now()->subDays(3)->toDateString(),
                'next_method' => 'c',
            ]);
        }

        $this->actingAs($user)->post(route('student.persons.start', 'free'));
        $free = PersonSession::where('user_id', $user->id)->where('kind', 'free')->firstOrFail();
        $types = $this->play($user, $free);

        // 5 личностей → соответствие, ещё 2 → соответствие по последним пяти.
        $this->assertSame(['c', 'c', 'c', 'c', 'c', 'd', 'c', 'c', 'd'], $types);
        $this->assertNull($free->refresh()->corm_awarded);
    }

    /** @test */
    public function a_surname_written_in_the_clue_is_not_asked()
    {
        $trainer = app(PersonTrainer::class);
        $person = new Person(['answer' => 'Стаханов', 'description' => 'Шахтёр.', 'association' => 'Стахановское движение (1935)']);

        $this->assertFalse($trainer->canType($person));
    }

    /** @test */
    public function the_all_persons_page_shows_every_card_and_marks_learned_ones()
    {
        $user = $this->student();
        $first = Person::orderBy('sort_order')->first();
        UserPerson::create([
            'user_id' => $user->id, 'person_id' => $first->id, 'step' => 1,
            'due_on' => now()->addDay()->toDateString(), 'learned_on' => now()->toDateString(), 'next_method' => 'b',
        ]);

        $this->actingAs($user)->get(route('student.persons.index'))->assertOk()->assertSee(route('student.persons.all'));
        $this->actingAs($user)->get(route('student.persons.all'))
            ->assertOk()
            ->assertSee($first->association)
            ->assertSee(Person::orderByDesc('sort_order')->first()->name)
            ->assertSee('✓ Изучено');
    }

    /** @test */
    public function the_trainer_is_closed_without_access()
    {
        $user = User::factory()->create(['role' => User::ROLE_READER]);

        $this->actingAs($user)->get(route('student.persons.index'))->assertForbidden();
    }
}
