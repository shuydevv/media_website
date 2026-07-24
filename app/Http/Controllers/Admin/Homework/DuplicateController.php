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
        $copy->save();

        foreach ($homework->tasks as $task) {
            $taskCopy = $task->replicate();
            $taskCopy->homework_id = $copy->id;
            $taskCopy->save();
        }

        return redirect()->route('admin.homeworks.edit', $copy)->with('success', 'Домашка продублирована.');
    }
}
