<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Homework;
use App\Models\HomeworkTask;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubmissionController extends Controller
{
public function create(Request $request, Homework $homework)
{
    $user = $request->user();

    $rawMax = $homework->attempts_allowed;      // может быть null/0 => безлимит
    $isUnlimited = empty($rawMax) || (int)$rawMax === 0;
    $attemptsAllowed = $isUnlimited ? null : (int)$rawMax;

    $attemptsUsed = Submission::where('user_id', $user->id)
        ->where('homework_id', $homework->id)
        ->count();

    // NEW: если попытка уже существует — сразу показываем результаты
    $existing = Submission::where('homework_id', $homework->id)
        ->where('user_id', $user->id)
        ->latest('id')
        ->first();

    if ($existing) {
        return redirect()->route('student.submissions.show', $existing);
    }

    if (!$isUnlimited && $attemptsUsed >= $attemptsAllowed) {
        // Лучше вести на результат последней попытки, а не back()
        $last = Submission::where('user_id', $user->id)
            ->where('homework_id', $homework->id)
            ->latest('id')->first();

        return $last
            ? redirect()->route('student.submissions.show', $last)
                ->withErrors(['attempts' => 'Лимит попыток исчерпан.'])
            : back()->withErrors(['attempts' => 'Лимит попыток исчерпан.']);
    }

    return view('student.submissions.create', [
        'homework'         => $homework,
        'attempts_used'    => $attemptsUsed,
        'attempts_allowed' => $attemptsAllowed, // null => безлимит
        'next_attempt_no'  => $attemptsUsed + 1,
        'is_unlimited'     => $isUnlimited,
    ]);
}

    public function store(Request $request, Homework $homework)
    {
        $user = $request->user();

        $data = $request->validate([
            'answers'   => 'array',
            'answers.*' => 'nullable|string',
        ]);
        $answers = $data['answers'] ?? [];

        // лимит попыток
        $attemptsAllowed = (int)($homework->attempts_allowed ?? 2);
        $attemptsUsed = Submission::where('user_id', $user->id)
            ->where('homework_id', $homework->id)
            ->count();

        if ($attemptsUsed >= $attemptsAllowed) {
            return back()->withErrors(['attempts' => 'Вы исчерпали лимит попыток по этой работе.']);
        }

        // создаём НОВУЮ попытку
        $submission = Submission::create([
            'homework_id' => $homework->id,
            'user_id'     => $user->id,
            'attempt_no'  => $attemptsUsed + 1,
            'answers'     => $answers,
            'status'      => 'pending',
        ]);

        // === ВАЖНО ===
        // Не трогаем модель Homework и её связи, берём задания напрямую из таблицы homework_tasks
        $tasks = HomeworkTask::where('homework_id', $homework->id)->get();

        // автопроверка (новый метод с передачей списка задач)
        $grader = app(\App\Service\Homework\AutoGrader::class);
        $grade  = method_exists($grader, 'gradeWithTasks')
            ? $grader->gradeWithTasks($tasks, $answers)
            : $grader->grade($homework, $answers); // fallback, если кто-то не обновил сервис

        $submission->autocheck_score   = (int)($grade['score'] ?? 0);
        $submission->total_score       = (int)($grade['score'] ?? 0);
        $submission->per_task_results  = $grade['per_task'] ?? null;

        // полностью авто?
        $hasManual = $tasks->contains(fn($t) => in_array($t->type, ['written','image_written','image_manual']));
        $submission->status = (!$hasManual && !empty($grade['fully_auto'])) ? 'checked' : 'pending';

        // просрочка — фиксируем статус (без штрафов)
        if (!empty($homework->due_at) && now()->isAfter($homework->due_at)) {
            $submission->status = 'expired';
        }

        $submission->save();

        // Редиректим на страницу результата попытки
        return redirect()
            ->route('student.submissions.show', $submission)
            ->with('success', 'Ответ отправлен');
    }

    public function show(Request $request, Submission $submission)
    {
        // только владелец
        if ($submission->user_id !== $request->user()->id) {
            abort(403);
        }

        // НЕ используем Eloquent-связь homework() — берём «плоский» объект и задачи
        $hwRow = DB::table('homeworks')->where('id', $submission->homework_id)->first();
        $tasks = HomeworkTask::where('homework_id', $submission->homework_id)->get();

        // Сконструируем лёгкий объект в том же формате, который ждёт шаблон
        $homework = (object)[
            'id'    => $submission->homework_id,
            'title' => $hwRow->title ?? 'Домашняя работа',
            'tasks' => $tasks,
        ];

        // история попыток пользователя по этой работе
        $attempts = Submission::where('user_id', $submission->user_id)
            ->where('homework_id', $submission->homework_id)
            ->orderByDesc('created_at')
            ->get(['id','attempt_no','autocheck_score','total_score','status','created_at']);

        return view('student.submissions.show', compact('submission','homework','attempts'));
    }
}
