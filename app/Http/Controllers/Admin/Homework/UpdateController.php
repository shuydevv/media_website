<?php

namespace App\Http\Controllers\Admin\Homework;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Homework\UpdateRequest;
use App\Models\Course;
use App\Models\Homework;
use App\Models\HomeworkTask;
use App\Models\Lesson;
use App\Models\Task;
use App\Service\ImageCompressor;

class UpdateController extends Controller
{
    use NormalizesTaskContent;

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

        $homework->update([
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'type'        => $data['type'],
            'due_at'      => $data['due_at'] ?? null,
            'course_id'   => $data['course_id'],
            'lesson_id'   => $data['lesson_id'],
        ]);

        if (isset($data['tasks']) && is_array($data['tasks'])) {
            $taskIds = [];

            foreach ($data['tasks'] as $taskData) {
                $taskIds[] = $this->saveTaskLink($homework, $taskData, (int) $data['course_id']);
            }

            $homework->tasks()->whereNotIn('id', $taskIds)->delete();
        }

        return redirect()->route('admin.homeworks.edit', $homework)->with('status', 'Домашка обновлена');
    }

    private function saveTaskLink(Homework $homework, array $taskData, int $courseId): int
    {
        $homeworkTask = isset($taskData['id'])
            ? HomeworkTask::where('homework_id', $homework->id)->find($taskData['id'])
            : null;
        $homeworkTask ??= new HomeworkTask(['homework_id' => $homework->id]);

        $source = $taskData['source'] ?? 'own';
        $overrideScore = (isset($taskData['max_score']) && $taskData['max_score'] !== '')
            ? (int) $taskData['max_score']
            : null;

        if ($source === 'bank' && !empty($taskData['task_id'])) {
            // Переключили на банк (или просто пересохранили) — своё
            // содержание больше не нужно, задание теперь читается через
            // task_id (см. HomeworkTask::getAttribute()).
            $homeworkTask->fill([
                'homework_id' => $homework->id,
                'task_id'     => (int) $taskData['task_id'],
                'order'       => $taskData['order'] ?? null,
                'max_score'   => $overrideScore,
            ])->save();

            return $homeworkTask->id;
        }

        $content = $this->normalizeTaskContent($taskData);

        $imagePath = $homeworkTask->getRawOriginal('image_path');
        if (!empty($taskData['image']) && $taskData['image'] instanceof \Illuminate\Http\UploadedFile) {
            $imagePath = ImageCompressor::forContent()->storeAs($taskData['image'], 'homework_images');
        }

        $homeworkTask->fill(array_merge($content, [
            'homework_id' => $homework->id,
            'image_path'  => $imagePath,
            'order'       => $taskData['order'] ?? null,
            'max_score'   => $overrideScore ?? 1,
            // Переключили с банка обратно на своё — отвязываем.
            'task_id'     => null,
        ]))->save();

        if (!empty($taskData['save_to_bank'])) {
            $this->copyIntoBank($homeworkTask, $content, $imagePath, $overrideScore, $courseId);
        }

        return $homeworkTask->id;
    }

    private function copyIntoBank(HomeworkTask $homeworkTask, array $content, ?string $imagePath, ?int $overrideScore, int $courseId): void
    {
        $categoryId = Course::find($courseId)?->category_id;
        if (!$categoryId) {
            return;
        }

        $bankTask = Task::create(array_merge($content, [
            'category_id' => $categoryId,
            'image_path'  => $imagePath,
            'max_score'   => $overrideScore ?? 1,
        ]));

        $homeworkTask->task_id = $bankTask->id;
        $homeworkTask->save();
    }
}
