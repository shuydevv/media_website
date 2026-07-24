<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Task;
use App\Models\User;
use Tests\TestCase;

/**
 * Регресс-тест на баг: форма банка заданий шлёт options/matches.left/
 * matches.right/image_auto_options ОДНОЙ textarea-строкой, а не массивом,
 * но валидация одно время требовала 'array' — любое сохранение задания
 * падало с "The options must be an array." См. TaskContentRules — эти же
 * правила теперь общие с конструктором домашки, так что тест заодно
 * закрепляет их совместимость.
 *
 * БД реальная (MyISAM, RefreshDatabase не используется в этом проекте —
 * см. закомментированный DB_CONNECTION=sqlite в phpunit.xml), поэтому все
 * созданные строки удаляются вручную в tearDown().
 */
class TaskControllerValidationTest extends TestCase
{
    private array $createdTaskIds = [];

    protected function tearDown(): void
    {
        if ($this->createdTaskIds) {
            Task::whereIn('id', $this->createdTaskIds)->delete();
        }
        parent::tearDown();
    }

    private function admin(): User
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();
        $this->assertNotNull($admin, 'В БД нет ни одного администратора — тест не может авторизоваться.');
        return $admin;
    }

    private function category(): Category
    {
        $category = Category::first();
        $this->assertNotNull($category, 'В БД нет ни одной категории — тест не может создать задание.');
        return $category;
    }

    public static function taskTypePayloads(): array
    {
        return [
            'test' => [[
                'type' => 'test', 'question_text' => 'Q', 'answer' => '2',
                'options' => "Вариант 1\nВариант 2\nВариант 3",
            ]],
            'text_with_questions' => [[
                'type' => 'text_with_questions', 'passage_text' => 'Passage', 'question_text' => 'Q', 'answer' => 'A',
            ]],
            'matching' => [[
                'type' => 'matching', 'question_text' => 'Q',
                'left_title' => 'L', 'right_title' => 'R',
                'matches' => ['left' => "A\nB\nC", 'right' => "1\n2\n3"],
            ]],
            'image_auto' => [[
                'type' => 'image_auto', 'question_text' => 'Q',
                'image_auto_options' => "Опция 1\nОпция 2",
            ]],
            'image_manual' => [[
                'type' => 'image_manual', 'question_text' => 'Q', 'answer' => 'A',
            ]],
            'written' => [[
                'type' => 'written', 'question_text' => 'Q', 'answer' => 'A',
            ]],
            'table' => [[
                'type' => 'table', 'question_text' => 'Q',
                'table_content' => '{"cols":["A","B"],"rows":[["x","y"]]}',
                'order_matters' => '1',
            ]],
        ];
    }

    /**
     * @test
     * @dataProvider taskTypePayloads
     */
    public function bank_task_saves_without_validation_errors_for_every_type(array $extra)
    {
        $payload = array_merge([
            'category_id' => $this->category()->id,
            'number' => '999',
            'max_score' => 1,
        ], $extra);

        $response = $this->actingAs($this->admin())->post(route('admin.tasks.store'), $payload);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertStringContainsString('/admin/tasks/', $response->headers->get('Location'));

        $id = (int) basename($response->headers->get('Location'));
        $this->createdTaskIds[] = $id;

        $task = Task::find($id);
        $this->assertNotNull($task);
        $this->assertSame($extra['type'], $task->type);
    }

    /** @test */
    public function editing_a_saved_task_with_options_does_not_fail_validation()
    {
        $admin = $this->admin();
        $category = $this->category();

        $createResponse = $this->actingAs($admin)->post(route('admin.tasks.store'), [
            'category_id' => $category->id,
            'number' => '999',
            'type' => 'test',
            'question_text' => 'Q',
            'answer' => '1',
            'options' => "О1\nО2",
            'max_score' => 1,
        ]);
        $id = (int) basename($createResponse->headers->get('Location'));
        $this->createdTaskIds[] = $id;

        $updateResponse = $this->actingAs($admin)->put(route('admin.tasks.update', $id), [
            'category_id' => $category->id,
            'number' => '999',
            'type' => 'test',
            'question_text' => 'Q edited',
            'answer' => '2',
            'options' => "О1-edited\nО2-edited\nО3-edited",
            'max_score' => 1,
        ]);

        $updateResponse->assertSessionHasNoErrors();
        $updateResponse->assertRedirect();

        $task = Task::find($id);
        $this->assertSame(['О1-edited', 'О2-edited', 'О3-edited'], $task->options);
    }
}
