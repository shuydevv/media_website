<?php

namespace Tests\Feature\Mentor;

use App\Models\Course;
use App\Models\CourseUser;
use App\Models\Homework;
use App\Models\HomeworkTask;
use App\Models\Submission;
use App\Models\User;
use App\Service\Homework\StudentReviewSummary;
use Tests\TestCase;

/** Страница проверки работы: контекст сдачи, время/подсказка, образец, карточка ученика, итог. */
class SubmissionReviewPageTest extends TestCase
{
    private function homework(Course $course, string $number, array $extra = []): array
    {
        $hw = Homework::create(['course_id' => $course->id, 'title' => 'Домашка ' . $number, 'type' => 'homework', 'attempts_allowed' => 1] + $extra);
        $auto = HomeworkTask::create(['homework_id' => $hw->id, 'type' => 'test', 'question_text' => 'Тест', 'answer' => '1', 'max_score' => 1, 'order' => 1]);
        $written = HomeworkTask::create([
            'homework_id' => $hw->id, 'type' => 'written', 'question_text' => 'Развёрнутый вопрос',
            'answer' => 'Образцовый ответ про налоги', 'hint' => 'Вспомни признаки налога',
            'max_score' => 3, 'order' => 2, 'task_number' => $number,
        ]);

        return [$hw, $auto, $written];
    }

    /** @test */
    public function page_shows_context_meta_exemplar_student_card_and_total()
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $student = User::factory()->create(['role' => User::ROLE_READER, 'first_name' => 'Анна', 'last_name' => 'Смирнова']);
        $course = Course::create(['title' => 'Обществознание 2027', 'description' => 'Тест', 'price_cents' => 100000]);
        CourseUser::create(['user_id' => $student->id, 'course_id' => $course->id, 'status' => 'active', 'enrolled_at' => now()]);

        // Две прошлые проверенные работы с 19-м: 1 из 3 и 0 из 3 — слабое место.
        foreach ([1, 0] as $score) {
            [$hw, $auto, $w] = $this->homework($course, '19');
            Submission::create([
                'homework_id' => $hw->id, 'user_id' => $student->id, 'status' => 'checked', 'total_score' => $score + 1,
                'per_task_results' => [(string) $auto->id => ['score' => 1], (string) $w->id => ['score' => $score]],
            ]);
        }

        [$hw, $auto, $w] = $this->homework($course, '19', ['due_at' => now()->subDays(2)]);
        $submission = Submission::create([
            'homework_id' => $hw->id, 'user_id' => $student->id, 'status' => 'pending',
            'autocheck_score' => 1, 'submitted_at' => now(),
            'answers' => [(string) $auto->id => '1', (string) $w->id => 'Ответ ученицы'],
            'per_task_results' => [(string) $auto->id => ['score' => 1]],
            'task_meta' => [(string) $w->id => ['seconds' => 185, 'hint' => true]],
        ]);

        $summary = app(StudentReviewSummary::class)->for($student, $submission);
        $this->assertSame(['Обществознание 2027'], $summary['courses']);
        $this->assertSame(2, $summary['checked']);
        $this->assertSame(['number' => '19', 'score' => 1, 'max' => 6, 'count' => 2, 'percent' => 17, 'weak' => true], $summary['numbers'][0]);

        $this->actingAs($admin)->get(route('mentor.submissions.show', $submission))
            ->assertOk()
            ->assertSee('Анна Смирнова')
            ->assertSee('Курс:')->assertSee('Обществознание 2027')
            ->assertSee('с опозданием на 2 дн.')
            ->assertSee('⏱ 3 мин')->assertSee('подсказка открыта')
            ->assertSee('Образцовый ответ про налоги')->assertSee('Вспомни признаки налога')
            ->assertSee('№19: 17%')
            ->assertSee('data-auto="1"', false)->assertSee('из 4');
    }
}
