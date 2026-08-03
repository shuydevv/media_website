<?php

namespace App\Http\Controllers\Admin\Homework;

use App\Http\Controllers\Controller;
use App\Models\Homework;

/**
 * Копия домашки в один клик — вместе со всеми её заданиями. Банковские
 * ссылки (task_id) переносятся как есть, «свои» задания копируются
 * целиком со своим содержанием. image_path не копируется физически: см.
 * комментарий у Admin\TaskController::duplicate() — тот же приём, файл
 * никогда не перезаписывается, значит безопасно ссылаться на тот же путь.
 */
class DuplicateController extends Controller
{
    public function __invoke(Homework $homework)
    {
        $homework->load('tasks');

        $copy = $homework->replicate();
        $copy->title = $homework->title . ' (копия)';
        // На homeworks.lesson_id — уникальный индекс (1 урок = 1 домашка).
        // replicate() копирует lesson_id как есть, и save() падал с
        // QueryException(23000) на любой домашке, привязанной к уроку —
        // то есть почти всегда. Копия не может делить урок с оригиналом,
        // поэтому оставляем её непривязанной — админ сам прикрепит к
        // нужному уроку через редактирование.
        $copy->lesson_id = null;
        $copy->save();

        foreach ($homework->tasks as $task) {
            $taskCopy = $task->replicate();
            $taskCopy->homework_id = $copy->id;
            $taskCopy->save();
        }

        return redirect()->route('admin.homeworks.edit', $copy)->with('success', 'Домашка продублирована.');
    }
}
