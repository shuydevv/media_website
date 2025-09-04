<?php

namespace App\Http\Controllers\Admin\Homework;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Homework\StoreRequest;
use App\Models\Homework;
use App\Models\HomeworkTask;
use Illuminate\Support\Facades\Storage;

class StoreController extends Controller
{
    public function __invoke(StoreRequest $request)
    {
        // dd($request->all());
        $validated = $request->validated();
        
        // Создание домашней работы
        $homework = Homework::create([
            'title'       => $validated['title'],
            'description' => $validated['description'] ?? null,
            'type'        => $validated['type'],
            'course_id' => $request->course_id,
            'lesson_id' => $request->lesson_id,

        ]);

        // Обработка задач (если есть)
        if (!empty($validated['tasks'])) {
            foreach ($validated['tasks'] as $task) {
                $imagePath = null;

                // Загрузка изображения (если есть)
                if (isset($task['image']) && $task['image']->isValid()) {
                    $imagePath = $task['image']->store('homework_images', 'public');
                }

                $type = $task['type'] ?? null;

                // Нормализуем image_auto_options к массиву строк
                $imageAutoOptions = [];
                if (array_key_exists('image_auto_options', $task)) {
                    $src = $task['image_auto_options'];
                    if (is_string($src)) {
                        $imageAutoOptions = array_values(array_filter(array_map('trim', preg_split('/\R/u', $src)), fn($v) => $v !== ''));
                    } elseif (is_array($src)) {
                        $imageAutoOptions = array_values(array_filter(array_map('trim', $src), fn($v) => $v !== ''));
                    }
                }

                $tableContent = null;
                if (($task['type'] ?? null) === 'table') {
                    $raw = trim((string)($task['table_content'] ?? ''));
                    if ($raw !== '') {
                        $decoded = json_decode($raw, true);
                        $tableContent = is_array($decoded) ? $decoded : null; // если кривая строка — просто null
                    }
                }

                HomeworkTask::create([
                    'homework_id'   => $homework->id,
                    'type'          => $task['type'],
                    'question_text' => $task['question_text'] ?? null,
                    'passage_text'  => $task['passage_text'] ?? null,
                    'options'       => $task['options'] ?? [],
                    'matches'       => $task['matches'] ?? [],
                    'table_content' => $tableContent,
                    'image_path'    => $imagePath,
                    'answer'        => $task['answer'],
                    'order'         => $task['order'] ?? null,
                    // 'task_number'   => $task['task_number'] ?? null,
                    'left_title'   => $task['left_title'] ?? null,
                    'right_title'  => $task['right_title'] ?? null,
                    'max_score'     => isset($task['max_score']) ? (int)$task['max_score'] : 0,
                    'task_id' => $task['task_id'],
                    'image_auto_options' => $imageAutoOptions,
                ]);
            }
        }

        return redirect()->route('admin.homeworks.index')
            ->with('success', 'Домашняя работа успешно создана');
    }
}
