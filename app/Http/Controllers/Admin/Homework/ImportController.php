<?php

namespace App\Http\Controllers\Admin\Homework;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Homework;
use App\Models\HomeworkTask;
use App\Models\Task;
use App\Support\TaskContentNormalizer;
use App\Support\TaskContentRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Массовая загрузка домашки через JSON-файл: {title, description, course_id,
 * type, due_at, tasks: [...]}. Каждый элемент tasks — либо {"task_id": N}
 * (взять из банка), либо полный объект содержания (создать «своё», как в
 * конструкторе домашки). Изображения не переносятся — только текст.
 */
class ImportController extends Controller
{
    use NormalizesTaskContent;

    public function create()
    {
        return view('admin.homeworks.import');
    }

    public function store(Request $request)
    {
        $request->validate(['file' => ['required', 'file']], [], ['file' => 'Файл']);

        $raw = file_get_contents($request->file('file')->getRealPath());
        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return back()->withErrors(['file' => 'Не удалось разобрать JSON: ' . json_last_error_msg()]);
        }

        $topRules = Validator::make($decoded ?? [], [
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'course_id'   => ['required', 'integer', 'exists:courses,id'],
            'type'        => ['nullable', 'string'],
            'due_at'      => ['nullable', 'date'],
            'tasks'       => ['nullable', 'array'],
        ], [], [
            'title' => 'Название', 'course_id' => 'Курс',
        ]);

        if ($topRules->fails()) {
            return back()->withErrors($topRules)->withInput();
        }

        $data = $topRules->validated();

        $homework = Homework::create([
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'type'        => $data['type'] ?? 'homework',
            'due_at'      => $data['due_at'] ?? null,
            'course_id'   => $data['course_id'],
        ]);

        $results = [];
        $created = 0;
        $tasks = $decoded['tasks'] ?? [];

        foreach ($tasks as $i => $item) {
            if (!is_array($item)) {
                $results[] = ['index' => $i, 'ok' => false, 'message' => 'Строка не является объектом'];
                continue;
            }

            if (isset($item['task_id'])) {
                if (!Task::whereKey($item['task_id'])->exists()) {
                    $results[] = ['index' => $i, 'ok' => false, 'message' => "Задание из банка id={$item['task_id']} не найдено"];
                    continue;
                }
                HomeworkTask::create([
                    'homework_id' => $homework->id,
                    'task_id'     => (int) $item['task_id'],
                    'order'       => $item['order'] ?? ($i + 1),
                    'max_score'   => isset($item['max_score']) ? (int) $item['max_score'] : null,
                ]);
                $created++;
                $results[] = ['index' => $i, 'ok' => true, 'note' => 'из банка'];
                continue;
            }

            $flat = TaskContentNormalizer::flattenImportShapes($item);
            $validator = Validator::make($flat, TaskContentRules::rules('bank'), [], TaskContentRules::attributes());
            if ($validator->fails()) {
                $results[] = ['index' => $i, 'ok' => false, 'message' => implode(' ', $validator->errors()->all())];
                continue;
            }

            $content = $this->normalizeTaskContent($validator->validated());

            $homeworkTask = HomeworkTask::create(array_merge($content, [
                'homework_id' => $homework->id,
                'order'       => $item['order'] ?? ($i + 1),
                'max_score'   => isset($item['max_score']) ? (int) $item['max_score'] : 1,
            ]));

            if (!empty($item['save_to_bank'])) {
                $categoryId = Course::find($data['course_id'])?->category_id;
                if ($categoryId) {
                    // Баллы в банк не копируются — там они общие для номера
                    // (TaskCriteria::max_score), не своя колонка на Task.
                    $bankTask = Task::create(array_merge($content, [
                        'category_id' => $categoryId,
                    ]));
                    $homeworkTask->task_id = $bankTask->id;
                    $homeworkTask->save();
                }
            }

            $created++;
            $results[] = ['index' => $i, 'ok' => true, 'note' => 'своё содержание'];
        }

        return view('admin.homeworks.import', [
            'results'  => $results,
            'created'  => $created,
            'total'    => count($tasks),
            'homework' => $homework,
        ]);
    }
}
