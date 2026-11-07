<?php

namespace Tests\Feature\Bank;

use App\Models\Category;
use App\Models\Task;
use App\Models\TaskCriteria;
use App\Service\Bank\BankImporter;
use Tests\TestCase;

/**
 * bank:import — заливка, перезаливка без дублей, правки автора важнее
 * файла, скрытие пропавших, критерии номеров.
 */
class BankImportTest extends TestCase
{
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->category = Category::create(['title' => 'Обществознание']);
    }

    private function data(array $override = []): array
    {
        return [
            'max_by_number' => [2 => 2, 19 => 3],
            'tasks' => array_values(array_replace([
                'a' => [
                    'ext' => 100, 'number' => 2, 'block' => 'economy', 'theme' => 'Инфляция',
                    'type' => 'test', 'question' => 'Выберите верные суждения об инфляции.',
                    'options' => ['раз', 'два', 'три', 'четыре', 'пять'], 'answer' => '135',
                    'explanation' => 'Разбор.', 'status' => 'active', 'also' => [101],
                ],
                'b' => [
                    'ext' => 200, 'number' => 19, 'block' => 'law', 'theme' => 'Право',
                    'type' => 'written', 'question' => 'Приведите три примера.',
                    'answer' => '1. Пример.', 'status' => 'active', 'also' => [],
                ],
            ], $override)),
        ];
    }

    public function test_real_file_is_valid(): void
    {
        $data = json_decode((string) file_get_contents(base_path('docs/bank/tasks.json')), true);

        $this->assertSame([], BankImporter::validate($data));
        $this->assertGreaterThan(2500, count($data['tasks']));
    }

    public function test_import_reimport_and_author_edits(): void
    {
        $importer = app(BankImporter::class);

        $stats = $importer->apply($this->data(), $this->category);
        $this->assertSame(2, $stats['added']);
        $this->assertSame(2, $stats['criteria']);

        $task = Task::where('external_id', 100)->first();
        $this->assertSame('test', $task->type);
        $this->assertSame(['раз', 'два', 'три', 'четыре', 'пять'], $task->options);
        $this->assertSame(2, $task->max_score);
        $this->assertFalse($task->is_public);
        $this->assertStringContainsString('Задание 19', TaskCriteria::where('number', '19')->value('criteria'));

        // Повторный запуск — ничего не меняется и не дублируется.
        $stats = $importer->apply($this->data(), $this->category);
        $this->assertSame(['added' => 0, 'changed' => 0, 'kept' => 2], array_intersect_key($stats, ['added' => 1, 'changed' => 1, 'kept' => 1]));
        $this->assertSame(2, Task::count());

        // Автор поправил задание в админке — перезаливка его не трогает.
        $task->update(['question_text' => 'Исправлено автором.']);
        $changed = $this->data(['a' => ['question' => 'Новое из файла.'] + $this->data()['tasks'][0]]);
        $stats = $importer->apply($changed, $this->category);
        $this->assertSame(1, $stats['edited']);
        $this->assertSame('Исправлено автором.', $task->fresh()->question_text);

        // Не правленное — обновляется из файла.
        $other = $this->data(['b' => ['question' => 'Обновлённое условие.'] + $this->data()['tasks'][1]]);
        $importer->apply($other, $this->category);
        $this->assertSame('Обновлённое условие.', Task::where('external_id', 200)->value('question_text'));

        // Пропало из файла — скрыто, не удалено.
        $importer->apply(['max_by_number' => [], 'tasks' => [$this->data()['tasks'][0]]], $this->category);
        $this->assertSame('hidden', Task::where('external_id', 200)->value('status'));
    }

    public function test_command_dry_run_does_not_write(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bank');
        file_put_contents($path, json_encode($this->data(), JSON_UNESCAPED_UNICODE));

        $this->artisan('bank:import', ['path' => $path, '--dry-run' => true])
            ->expectsOutputToContain('новых 2')
            ->assertSuccessful();
        $this->assertSame(0, Task::count());

        $this->artisan('bank:import', ['path' => $path])->assertSuccessful();
        $this->assertSame(2, Task::forTrainer()->count());
        @unlink($path);
    }
}
