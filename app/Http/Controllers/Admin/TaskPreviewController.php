<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\TaskContentNormalizer;
use App\Support\TaskContentRules;
use Illuminate\Http\Request;

/**
 * Live-превью «как увидит студент» прямо в форме создания/редактирования
 * задания — рендерит тот же partial, что показывает сохранённое задание
 * (student.submissions.partials.task-prompt), по ещё НЕсохранённым данным
 * формы. Никакой модели не создаёт и не трогает БД.
 *
 * Валидация намеренно мягкая ('homework' режим без source — required_if
 * не срабатывает, если поля source нет вовсе): форма может быть заполнена
 * частично прямо во время печати, превью должно рендериться и тогда.
 */
class TaskPreviewController extends Controller
{
    public function __invoke(Request $request)
    {
        $u = auth()->user();
        abort_unless($u && (int) $u->role === User::ROLE_ADMIN, 403);

        $data = $request->validate(TaskContentRules::rules('homework'));

        $content = TaskContentNormalizer::normalize($data);
        $content['image_path'] = null; // превью картинки — на клиенте, через FileReader
        // task-prompt.blade.php обращается к $task->matches['left'] напрямую
        // (без ??) — normalize() отдаёт null, когда обе стороны пустые
        // (например, только что выбрали тип "Соотнесение" и ещё не начали
        // печатать), доступ к ключу массива на null уронил бы превью.
        $content['matches'] = $content['matches'] ?? ['left' => [], 'right' => []];

        return response(
            view('student.submissions.partials.task-prompt', ['task' => (object) $content])->render()
        );
    }
}
