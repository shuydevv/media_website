<?php

namespace Tests\Feature\Student;

use App\Models\HistEvent;
use App\Models\HistFact;
use App\Models\HistSession;
use App\Models\User;
use App\Models\UserHistEvent;
use App\Service\Dates\DateTrainer;
use App\Service\Dates\HistImporter;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Тренажёр дат целиком: заливка настоящей базы, порция новых через HTTP с
 * верными ответами, повторение на следующий день, доступ.
 */
class DateTrainerFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $data = json_decode(file_get_contents(base_path('docs/history/events_base.json')), true);
        app(HistImporter::class)->apply($data);
    }

    private function student(): User
    {
        $user = User::factory()->create(['role' => User::ROLE_READER]);
        DB::table('hist_trainer_users')->insert(['user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    /**
     * Отвечает на текущий шаг верно — так, как это сделала бы страница:
     * клетки — цифрами года, квиз — номером верного варианта.
     */
    private function answerRight(User $user, HistSession $session): void
    {
        $session->refresh();
        $current = $session->state['current'];
        $item = HistEvent::find($current['event_id']);

        match ($current['type']) {
            'card' => $this->actingAs($user)->post(route('student.dates.session.card', $session), ['remembered' => 1]),
            'a' => $this->actingAs($user)->post(route('student.dates.session.answer', $session), [
                'typed' => $item->year_start.($item->isRange() ? $item->year_end : ''),
            ]),
            'b' => $this->actingAs($user)->post(route('student.dates.session.choose', $session), [
                'choice' => array_search(
                    app(DateTrainer::class)->quizFacts($current)[$current['slot']]->id,
                    $current['options'],
                    true
                ),
            ]),
            'c' => $this->actingAs($user)->post(route('student.dates.session.choose', $session), [
                'choice' => array_search($current['odd'], $current['options'], true),
            ]),
        };

        $session->refresh();
        $current = $session->state['current'];
        if ($current !== null && (($current['type'] === 'a' && $current['result'] !== null) || (in_array($current['type'], ['b', 'c']) && $current['done']))) {
            $this->actingAs($user)->post(route('student.dates.session.next', $session));
        }
    }

    /** @test */
    public function the_base_is_imported_with_events_reigns_and_links()
    {
        $this->assertSame(100, HistEvent::where('kind', HistEvent::KIND_EVENT)->count());
        $this->assertGreaterThan(50, HistEvent::where('kind', HistEvent::KIND_REIGN)->count());

        $poltava = HistEvent::where('slug', 'poltava-1709')->first();
        $this->assertSame(['reign-peter1'], $poltava->reigns->pluck('slug')->all());
        $this->assertSame('northern-war-1700', HistEvent::find($poltava->parent_id)->slug);
    }

    /** @test */
    public function reimport_changes_nothing()
    {
        $data = json_decode(file_get_contents(base_path('docs/history/events_base.json')), true);
        $plan = app(HistImporter::class)->plan($data);

        $this->assertSame([], $plan['items']['changed']);
        $this->assertSame(0, $plan['facts']['changed'] + $plan['facts']['added'] + $plan['facts']['removed']);
    }

    /** @test */
    public function a_student_learns_a_batch_and_reviews_it_next_day()
    {
        $user = $this->student();

        $this->actingAs($user)->get(route('student.dates.index'))->assertOk()->assertSee('Тренажёр дат')
            ->assertSee('img/trainers/dates/repeating.webp', false)
            ->assertDontSee('img/mascot/terms/', false);

        $this->actingAs($user)->post(route('student.dates.start', 'learn'))->assertRedirect();
        $session = HistSession::where('user_id', $user->id)->where('kind', 'learn')->firstOrFail();
        $this->assertCount(5, $session->state['terms']);

        // Порция — первые пять из порядка изучения (поле order), не по годам.
        $data = json_decode(file_get_contents(base_path('docs/history/events_base.json')), true);
        $this->assertEqualsCanonicalizing(
            array_slice($data['order'][0]['ids'], 0, 5),
            HistEvent::whereIn('id', $session->state['terms'])->pluck('slug')->all()
        );

        $steps = 0;
        while (!$session->refresh()->finished_at && $steps++ < 60) {
            // Каждый шаг страница рисует без ошибок.
            $this->actingAs($user)->get(route('student.dates.session', $session))->assertOk();
            $this->answerRight($user, $session);
        }

        $session->refresh();
        $this->assertNotNull($session->finished_at);
        $this->assertSame(10, (int) $session->correct_first);
        $this->assertSame(3, (int) $session->corm_awarded);
        $this->assertSame(5, UserHistEvent::where('user_id', $user->id)->count());
        $this->actingAs($user)->get(route('student.dates.session', $session))->assertOk()->assertSee('с первой попытки');

        // Назавтра — повторение этих пяти.
        $this->artisan('dates:shift', ['user' => $user->id])->assertSuccessful();
        $this->actingAs($user)->post(route('student.dates.start', 'review'));
        $review = HistSession::where('user_id', $user->id)->where('kind', 'review')->firstOrFail();
        $this->assertSame(5, (int) $review->total);

        $steps = 0;
        while (!$review->refresh()->finished_at && $steps++ < 40) {
            $this->actingAs($user)->get(route('student.dates.session', $review))->assertOk();
            $this->answerRight($user, $review);
        }

        $this->assertNotNull($review->refresh()->finished_at);
        $this->assertSame(5, UserHistEvent::where('user_id', $user->id)->where('step', 2)->count());
    }

    /** @test */
    public function a_wrong_year_names_the_event_it_belongs_to()
    {
        $user = $this->student();
        $this->actingAs($user)->post(route('student.dates.start', 'learn'));
        $session = HistSession::where('user_id', $user->id)->firstOrFail();

        // Доходим до первого вопроса про год. Текущий шаг выбирается при
        // первом показе сессии.
        $this->actingAs($user)->get(route('student.dates.session', $session))->assertOk();
        $guard = 0;
        while ($session->refresh()->state['current']['type'] === 'card' && $guard++ < 10) {
            $this->actingAs($user)->post(route('student.dates.session.card', $session), ['remembered' => 1]);
        }
        $item = HistEvent::find($session->refresh()->state['current']['event_id']);
        // Год другого элемента той же формы.
        $other = HistEvent::where('id', '!=', $item->id)->get()
            ->first(fn ($o) => $o->isRange() === $item->isRange() && $o->year_end !== null
                && $o->yearsLabel() !== $item->yearsLabel());

        $response = $this->actingAs($user)->post(
            route('student.dates.session.answer', $session),
            ['typed' => $other->year_start.($other->isRange() ? $other->year_end : '')],
            ['HX-Request' => 'true']
        );

        $response->assertOk();
        $this->assertFalse($session->refresh()->state['current']['result']['correct']);
    }

    /** @test */
    public function distractors_come_from_the_same_kind()
    {
        $trainer = app(DateTrainer::class);
        $reign = HistEvent::where('slug', 'reign-peter1')->first();

        // Правления, пересекающиеся с Петром I, — Софья и Иван V — в
        // неверные варианты к нему не идут.
        $related = HistEvent::whereIn('id', $trainer->relatedIds($reign))->pluck('slug')->all();
        $this->assertContains('reign-sofia', $related);
        $this->assertContains('reign-ivan5', $related);
        $this->assertNotContains('reign-paul', $related);

        $this->assertSame(0, HistFact::whereIn('event_id', $trainer->relatedIds($reign))
            ->whereHas('event', fn ($q) => $q->where('kind', 'event'))->count());
    }

    /** @test */
    public function the_trainer_is_closed_without_access()
    {
        $user = User::factory()->create(['role' => User::ROLE_READER]);

        $this->actingAs($user)->get(route('student.dates.index'))->assertForbidden();
    }

    /** @test */
    public function the_session_mascot_is_dushnila_not_the_students_shark()
    {
        $user = $this->student();
        $this->actingAs($user)->post(route('student.dates.start', 'learn'));
        $session = HistSession::where('user_id', $user->id)->firstOrFail();

        $this->actingAs($user)->get(route('student.dates.session', $session))
            ->assertOk()
            ->assertSee('img/trainers/mascots/dushnila_normal.webp', false)
            ->assertDontSee('img/mascot/level-', false);
    }

    /** @test */
    public function another_students_session_is_not_found()
    {
        $owner = $this->student();
        $this->actingAs($owner)->post(route('student.dates.start', 'learn'));
        $session = HistSession::where('user_id', $owner->id)->firstOrFail();

        $this->actingAs($this->student())->get(route('student.dates.session', $session))->assertNotFound();
    }
}
