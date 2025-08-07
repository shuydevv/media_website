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

                HomeworkTask::create([
                    'homework_id'   => $homework->id,
                    'type'          => $task['type'],
                    'question_text' => $task['question_text'] ?? null,
                    'options'       => $task['options'] ?? [],
                    'matches'       => $task['matches'] ?? [],
                    'table'         => $task['table'] ?? [],
                    'image_path'    => $imagePath,
                    'answer'        => $task['answer'],
                    'order'         => $task['order'] ?? null,
                    'task_number'   => $task['task_number'] ?? null,
                ]);
            }
        }

        return redirect()->route('admin.homeworks.index')
            ->with('success', 'Домашняя работа успешно создана');
    }
}
