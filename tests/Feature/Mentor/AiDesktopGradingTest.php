<?php

namespace Tests\Feature\Mentor;

use App\Models\AiGradingBatch;
use App\Models\Category;
use App\Models\Course;
use App\Models\Homework;
use App\Models\HomeworkTask;
use App\Models\Submission;
use App\Models\User;
use App\Service\Homework\AiGradingStats;
use App\Service\Homework\DesktopGradingSkill;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** ИИ-проверка через Claude Desktop: выгрузка → результат → черновики, отмена, статистика. */
class AiDesktopGradingTest extends TestCase
{
    private User $admin;
    private Submission $submission;
    /** @var HomeworkTask[] */
    private array $written;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $student = User::factory()->create(['role' => User::ROLE_READER]);

        $category = Category::create(['title' => 'Обществознание']);
        $course = Course::create(['title' => 'Курс', 'description' => 'Тест', 'price_cents' => 100000, 'category_id' => $category->id]);
        $homework = Homework::create(['course_id' => $course->id, 'title' => 'Домашка', 'type' => 'homework', 'attempts_allowed' => 1]);

        $this->written = [
            HomeworkTask::create(['homework_id' => $homework->id, 'type' => 'written', 'question_text' => 'Вопрос 1', 'answer' => 'Образец 1', 'max_score' => 2, 'order' => 1, 'task_number' => '17']),
            HomeworkTask::create(['homework_id' => $homework->id, 'type' => 'written', 'question_text' => 'Вопрос 2', 'answer' => 'Образец 2', 'max_score' => 3, 'order' => 2, 'task_number' => '19']),
        ];
        $auto = HomeworkTask::create(['homework_id' => $homework->id, 'type' => 'test', 'question_text' => 'Тест', 'answer' => '1', 'max_score' => 1, 'order' => 3]);

        $this->submission = Submission::create([
            'homework_id' => $homework->id,
            'user_id'     => $student->id,
            'status'      => 'pending',
            'answers'     => [
                (string) $this->written[0]->id => 'Ответ ученика 1',
                (string) $this->written[1]->id => 'Ответ ученика 2',
                (string) $auto->id             => '1',
            ],
        ]);
    }

    private function exportFile(array $params = []): array
    {
        $response = $this->actingAs($this->admin)->post(route('mentor.review.ai_desktop.export'), $params)->assertOk();

        return json_decode($response->streamedContent(), true);
    }

    private function resultFile(array $export, array $overrides = []): UploadedFile
    {
        $results = array_map(fn ($item) => array_merge([
            'id' => $item['id'], 'elements' => [], 'extra_elements_with_errors' => [], 'criteria_scores' => [],
            'score' => 1, 'rationale' => 'Обоснование', 'comment' => 'Комментарий',
            'needs_human_review' => false, 'review_reason' => '',
        ], $overrides[$item['id']] ?? []), $export['items']);

        return UploadedFile::fake()->createWithContent('result.json', json_encode([
            'format' => 'poltavskiy-homework-check-result/v1', 'export_id' => $export['export_id'], 'results' => $results,
        ], JSON_UNESCAPED_UNICODE));
    }

    /** @test */
    public function full_cycle_export_import_creates_drafts_and_report()
    {
        $this->actingAs($this->admin)->get(route('mentor.submissions.index'))
            ->assertOk()->assertSee('ИИ-проверка через Claude')->assertSee('Обществознание: 2');

        $export = $this->exportFile(['subject' => 'Обществознание']);
        $this->assertCount(2, $export['items'], 'только письменные задания');
        $this->assertSame('obshchestvoznanie', $export['items'][0]['rules']);
        $this->assertSame(app(DesktopGradingSkill::class)->version(), $export['rules_version']);

        $this->actingAs($this->admin)->get(route('mentor.submissions.index'))->assertSee('Ждёт ИИ-проверки');

        $batch = AiGradingBatch::firstWhere('external_id', $export['export_id']);
        $again = json_decode($this->actingAs($this->admin)
            ->post(route('mentor.review.ai_desktop.redownload', $batch))->assertOk()->streamedContent(), true);
        $this->assertSame($export['export_id'], $again['export_id']);
        $this->assertCount(2, $again['items']);

        $reviewId = $export['items'][1]['id'];
        $this->actingAs($this->admin)
            ->post(route('mentor.review.ai_desktop.import'), ['result' => $this->resultFile($export, [
                $reviewId => ['needs_human_review' => true, 'review_reason' => 'Спорный пример', 'score' => 2],
            ])])
            ->assertRedirect()
            ->assertSessionHas('ai_report', fn ($r) => $r['imported'] === 2 && $r['review'] === [$this->submission->id => 1]);

        $drafts = $this->submission->fresh()->ai_drafts;
        $this->assertSame(1, $drafts[(string) $this->written[0]->id]['score']);
        $this->assertTrue($drafts[(string) $this->written[1]->id]['needs_review']);
        $this->assertSame('ended', $batch->fresh()->status);

        $this->actingAs($this->admin)->get(route('mentor.submissions.index'))->assertSee('ИИ-черновик: 2 из 2');
    }

    /** @test */
    public function cancel_returns_tasks_to_the_queue()
    {
        $export = $this->exportFile();
        $batch = AiGradingBatch::firstWhere('external_id', $export['export_id']);

        $this->actingAs($this->admin)->post(route('mentor.review.ai_desktop.cancel', $batch))->assertRedirect();

        $this->assertSame('canceled', $batch->fresh()->status);
        $this->assertSame([], $this->submission->fresh()->ai_drafts ?? []);
        $this->assertCount(2, $this->exportFile()['items'], 'отменённые задания снова выгружаются');
    }

    /** @test */
    public function mentor_only_admin_can_export()
    {
        $mentor = User::factory()->create(['role' => User::ROLE_MENTOR]);
        $this->actingAs($mentor)->post(route('mentor.review.ai_desktop.export'))->assertForbidden();
        $this->actingAs($mentor)->get(route('mentor.submissions.index'))->assertOk()->assertDontSee('ИИ-проверка через Claude');
    }

    /** @test */
    public function stats_compare_ai_draft_with_mentor_grade()
    {
        $export = $this->exportFile();
        $this->actingAs($this->admin)->post(route('mentor.review.ai_desktop.import'), ['result' => $this->resultFile($export)]);

        [$t1, $t2] = $this->written;
        // Задание 1: балл как у ИИ, куратор только убрал точку. Задание 2: балл исправлен, текст переписан.
        $this->actingAs($this->admin)->post(route('mentor.review.task.save', [$this->submission, $t1->id]), [
            'score' => 1, 'reason' => 'Обоснование.', 'comment' => 'комментарий',
        ]);
        $this->actingAs($this->admin)->post(route('mentor.review.task.save', [$this->submission, $t2->id]), [
            'score' => 3, 'reason' => 'Совсем другой текст про другое', 'comment' => 'И ещё одна мысль куратора',
        ]);

        $stats = app(AiGradingStats::class)->summary();
        $this->assertSame(2, $stats['total']);
        $this->assertSame(1, $stats['score_match']);
        $this->assertSame(1, $stats['text']['same']);
        $this->assertSame(1, $stats['text']['rewritten']);
        $this->assertSame(['total' => 1, 'score_match' => 1], $stats['by_number']['17']);
    }

    /** @test */
    public function text_change_ignores_punctuation_case_and_yo()
    {
        $this->assertSame(0.0, AiGradingStats::changeRatio('Всё верно.', 'все верно'));
        $this->assertSame('cosmetic', AiGradingStats::textBucket(AiGradingStats::changeRatio(
            'Пример засчитан: он развёрнутый и про российские реалии',
            'Пример засчитан: он развёрнутый и о российских реалиях',
        )));
    }
}
