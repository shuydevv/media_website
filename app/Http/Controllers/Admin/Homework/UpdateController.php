<?php

namespace App\Http\Controllers\Admin\Homework;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Homework\UpdateRequest;
use App\Models\Homework;
use App\Models\HomeworkTask;
use App\Models\Lesson;

class UpdateController extends Controller
{
public function __invoke(UpdateRequest $request, Homework $homework)
{
    $data = $request->validated();

    // Проверка соответствия lesson ↔ course
    $lessonOk = Lesson::query()
        ->join('course_sessions', 'course_sessions.id', '=', 'lessons.course_session_id')
        ->where('lessons.id', $data['lesson_id'])
        ->where('course_sessions.course_id', $data['course_id'])
        ->exists();

    if (!$lessonOk) {
        return back()->withErrors(['lesson_id' => 'Этот урок не относится к выбранному курсу'])->withInput();
    }

    // Обновляем саму домашку
    $homework->update([
        'title'       => $data['title'],
        'description' => $data['description'] ?? null,
        'type'        => $data['type'],
        'course_id'   => $data['course_id'],
        'lesson_id'   => $data['lesson_id'],
    ]);

    // Обновляем задачи
    if (isset($data['tasks']) && is_array($data['tasks'])) {
        $taskIds = [];

        foreach ($data['tasks'] as $taskData) {
            $task = isset($taskData['id'])
                ? HomeworkTask::where('homework_id', $homework->id)->find($taskData['id'])
                : new HomeworkTask(['homework_id' => $homework->id]);

            if (!$task) {
                $task = new HomeworkTask(['homework_id' => $homework->id]);
            }

            $task->fill([
                'type'          => $taskData['type'],
                'question_text' => $taskData['question_text'] ?? null,
                'options'       => $taskData['options'] ?? null,
                'matches'       => $taskData['matches'] ?? null,
                'table'         => $taskData['table'] ?? null,
                'answer'        => $taskData['answer'],
                'order'         => $taskData['order'] ?? null,
                'task_number'   => $taskData['task_number'] ?? null,
            ]);

            // если загрузили новое изображение — сохраним
            if (!empty($taskData['image']) && $taskData['image'] instanceof \Illuminate\Http\UploadedFile) {
                $path = $taskData['image']->store('homework_images', 'public');
                $task->image_path = $path;
            }

            $task->homework_id = $homework->id;
            $task->save();

            $taskIds[] = $task->id;
        }

        // Удаляем те, которых нет в форме
        $homework->tasks()->whereNotIn('id', $taskIds)->delete();
    }

    return redirect()->route('admin.homeworks.edit', $homework)->with('status', 'Домашка обновлена');
}

}
