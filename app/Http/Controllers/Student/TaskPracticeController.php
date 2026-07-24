<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Task;
use App\Models\TaskAttempt;
use App\Service\Homework\AutoGrader;
use Illuminate\Http\Request;

/**
 * Самостоятельное прорешивание банка заданий в личном кабинете — тот же
 * интерфейс, что и в домашке: авто-проверяемые задания показывают верно/
 * неверно (через тот же AutoGrader, что и SubmissionController), задания с
 * ручной проверкой сразу отдают образцовый ответ и комментарий — без
 * очереди на проверку куратором (это не домашка, а самопроверка).
 */
class TaskPracticeController extends Controller
{
    public function index(Request $request)
    {
        $q = Task::query()->whereNotNull('type')->with('category');

        if ($cid = $request->get('category_id')) {
            $q->where('category_id', (int) $cid);
        }
        if ($n = $request->get('number')) {
            $q->where('number', 'like', "%{$n}%");
        }

        $tasks = $q->orderByDesc('id')->paginate(20)->withQueryString();
        $categories = Category::orderBy('title')->get(['id', 'title']);

        $userId = $request->user()->id;
        $attemptedTaskIds = TaskAttempt::where('user_id', $userId)
            ->whereIn('task_id', $tasks->pluck('id'))
            ->pluck('status', 'task_id');

        return view('student.tasks.index', [
            'tasks' => $tasks,
            'categories' => $categories,
            'filters' => $request->only(['category_id', 'number']),
            'attemptedTaskIds' => $attemptedTaskIds,
        ]);
    }

    public function show(Request $request, Task $task)
    {
        abort_if(!$task->type, 404);

        $lastAttempt = TaskAttempt::where('task_id', $task->id)
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->first();

        return view('student.tasks.practice', [
            'task' => $task,
            'result' => null,
            'revealed' => false,
            'lastAttempt' => $lastAttempt,
        ]);
    }

    public function check(Request $request, Task $task)
    {
        abort_if(!$task->type, 404);

        $data = $request->validate(['answer' => 'nullable|string']);
        $answer = $data['answer'] ?? null;

        $revealed = false;
        $result = null;

        if ($task->isAutoGradable()) {
            $result = app(AutoGrader::class)->scoreOne($task, $answer);
            TaskAttempt::create([
                'task_id' => $task->id,
                'user_id' => $request->user()->id,
                'answer'  => $answer,
                'status'  => $result['status'],
                'score'   => $result['score'],
            ]);
        } else {
            // Ручная проверка в самостоятельном прорешивании не идёт к
            // куратору — сразу показываем образцовый ответ и комментарий.
            $revealed = true;
            TaskAttempt::create([
                'task_id' => $task->id,
                'user_id' => $request->user()->id,
                'answer'  => $answer,
                'status'  => null,
                'score'   => null,
            ]);
        }

        $lastAttempt = TaskAttempt::where('task_id', $task->id)
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->first();

        return view('student.tasks.practice', [
            'task' => $task,
            'result' => $result,
            'revealed' => $revealed,
            'checkAnswer' => $answer,
            'lastAttempt' => $lastAttempt,
        ]);
    }
}
