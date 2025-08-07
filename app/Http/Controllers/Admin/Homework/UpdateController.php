<?php

namespace App\Http\Controllers\Admin\Homework;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Homework\UpdateRequest;
use App\Models\Homework;
use App\Models\HomeworkTask;

class UpdateController extends Controller
{
    public function __invoke(UpdateRequest $request, Homework $homework)
    {
        $validated = $request->validated();

        // Обновляем поля домашнего задания
        $homework->update([
            'title'       => $validated['title'],
            'description' => $validated['description'] ?? null,
            'type'        => $validated['type'],
        ]);

        $taskIds = [];

        if (!empty($validated['tasks'])) {
            foreach ($validated['tasks'] as $taskData) {
                $task = isset($taskData['id'])
                    ? HomeworkTask::find($taskData['id'])
                    : new HomeworkTask(['homework_id' => $homework->id]);

                $task->fill([
                    'type'           => $taskData['type'],
                    'question_text'  => $taskData['question_text'] ?? null,
                    'options'        => $taskData['options'] ?? null,
                    'matches'        => $taskData['matches'] ?? null,
                    'table'          => $taskData['table'] ?? null,
                    'image_path'     => $taskData['image_path'] ?? null,
                    'answer'         => $taskData['answer'],
                    'order'          => $taskData['order'] ?? null,
                    'task_number'    => $taskData['task_number'] ?? null,
                ]);

                $task->homework_id = $homework->id;
                $task->save();

                $taskIds[] = $task->id;
            }
        }

        // Удалим старые задачи, которые не были переданы
        $homework->tasks()->whereNotIn('id', $taskIds)->delete();

        return redirect()->route('admin.homeworks.index')->with('success', 'Домашняя работа обновлена');
    }
}
