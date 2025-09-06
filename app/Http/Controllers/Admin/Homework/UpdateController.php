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
        'due_at'      => $data['due_at'],
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

            $type = $taskData['type'] ?? null;

            // Нормализация image_auto_options
            $imageAutoOptions = null;
            if (array_key_exists('image_auto_options', $taskData)) {
                $src = $taskData['image_auto_options'];
                if (is_string($src)) {
                    $imageAutoOptions = array_values(array_filter(array_map('trim', preg_split('/\R/u', $src)), fn($v) => $v !== ''));
                } elseif (is_array($src)) {
                    $imageAutoOptions = array_values(array_filter(array_map('trim', $src), fn($v) => $v !== ''));
                } else {
                    $imageAutoOptions = [];
                }
            }

            $tableContent = null;
            if (($taskData['type'] ?? null) === 'table') {
                $raw = trim((string)($taskData['table_content'] ?? ''));
                if ($raw !== '') {
                    $decoded = json_decode($raw, true);
                    $tableContent = is_array($decoded) ? $decoded : null;
                }
            }

            $task->fill([
                'type'          => $taskData['type'],
                'question_text' => $taskData['question_text'] ?? null,
                'passage_text'  => $taskData['passage_text'] ?? null,   // художественный / публицистический текст
                'answer'        => $taskData['answer'] ?? null,

                // JSON-поля
                'options'       => $taskData['options'] ?? null,        // варианты ответа (multiple_choice)
                'matches'       => $taskData['matches'] ?? null,        // соотнесение
                'table_content' => $tableContent,          // содержимое таблицы (3x4)

                // служебные
                // 'task_number'   => $taskData['task_number'] ?? null,
                'task_id' => $taskData['task_id'],
                'order'         => $taskData['order'] ?? null,

                // для картинок
                'image_path'    => $taskData['image_path'] ?? $task->image_path, 

                // дополнительные (если есть в миграциях)
                'left_title'   => $taskData['left_title'] ?? null,
                'right_title'  => $taskData['right_title'] ?? null,
                'max_score'     => isset($taskData['max_score']) ? (int)$taskData['max_score'] : 1,

                'image_auto_options' => $imageAutoOptions,

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
