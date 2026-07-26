<?php

namespace App\Http\Controllers\Admin\Homework;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Homework\StoreRequest;
use App\Models\Course;
use App\Models\Homework;
use App\Models\HomeworkTask;
use App\Models\Task;
use App\Service\ImageCompressor;

class StoreController extends Controller
{
    use NormalizesTaskContent;

    public function __invoke(StoreRequest $request)
    {
        $validated = $request->validated();

        $homework = Homework::create([
            'title'       => $validated['title'],
            'description' => $validated['description'] ?? null,
            'type'        => $validated['type'],
            'due_at'      => $validated['due_at'] ?? null,
            'mock_number' => $validated['mock_number'] ?? null,
            'course_id'   => $request->course_id,
            'lesson_id'   => $request->lesson_id,
        ]);

        if (!empty($validated['tasks'])) {
            foreach ($validated['tasks'] as $taskData) {
                $this->saveTaskLink($homework, $taskData, (int) $request->course_id);
            }
        }

        return redirect()->route('admin.homeworks.index')
            ->with('success', 'Домашняя работа успешно создана');
    }

    /**
     * Задание — либо связка с уже существующим банковским Task («из банка»),
     * либо содержание сохраняется прямо на HomeworkTask («только для этой
     * домашки», как раньше). У обоих режимов общие order/max_score.
     */
    private function saveTaskLink(Homework $homework, array $taskData, int $courseId): void
    {
        $source = $taskData['source'] ?? 'own';
        $overrideScore = (isset($taskData['max_score']) && $taskData['max_score'] !== '')
            ? (int) $taskData['max_score']
            : null;

        if ($source === 'bank' && !empty($taskData['task_id'])) {
            HomeworkTask::create([
                'homework_id' => $homework->id,
                'task_id'     => (int) $taskData['task_id'],
                'order'       => $taskData['order'] ?? null,
                'max_score'   => $overrideScore,
            ]);

            return;
        }

        $imagePath = null;
        if (isset($taskData['image']) && $taskData['image']->isValid()) {
            $imagePath = ImageCompressor::forContent()->storeAs($taskData['image'], 'homework_images');
        }

        $content = $this->normalizeTaskContent($taskData);
        $number = ($taskData['number'] ?? null) ?: null;

        $homeworkTask = HomeworkTask::create(array_merge($content, [
            'homework_id' => $homework->id,
            'image_path'  => $imagePath,
            'order'       => $taskData['order'] ?? null,
            'max_score'   => $overrideScore ?? 1,
            'task_number' => $number,
        ]));

        if (!empty($taskData['save_to_bank'])) {
            $this->copyIntoBank($homeworkTask, $content, $imagePath, $courseId, $number);
        }
    }

    /**
     * «Также сохранить в банк заданий» — то же содержание, отдельной записью
     * в Task, категорию берём с курса домашки (в этой форме своего выбора
     * категории нет — задать/поменять её можно потом со страницы банка).
     * Если у курса нет категории — не блокируем сохранение домашки, просто
     * не кладём задание в банк.
     */
    private function copyIntoBank(HomeworkTask $homeworkTask, array $content, ?string $imagePath, int $courseId, ?string $number = null): void
    {
        $categoryId = Course::find($courseId)?->category_id;
        if (!$categoryId) {
            return;
        }

        // Баллы в Task не копируются — они общие для номера (см.
        // TaskCriteria::max_score), не своя колонка на Task.
        $bankTask = Task::create(array_merge($content, [
            'category_id' => $categoryId,
            'image_path'  => $imagePath,
            'number'      => $number,
        ]));

        $homeworkTask->task_id = $bankTask->id;
        $homeworkTask->save();
    }
}
