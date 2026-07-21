<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Homework;
use App\Models\HomeworkTask;
use App\Models\Submission;
use App\Service\FishFoodService;
use App\Service\Homework\AutoGrader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubmissionController extends Controller
{
    /**
     * Точка входа: продолжить незавершённую попытку или начать новую
     * и сразу отправить на первый вопрос.
     */
    public function create(Request $request, Homework $homework)
    {
        $this->authorize('view', $homework);

        // Пока урок, к которому привязана домашка, ещё не наступил, доступа
        // к ней быть не должно вообще — 404, а не 403, чтобы прямой ссылкой
        // тоже нельзя было узнать о её существовании (см. Homework::isLessonUpcoming()).
        abort_if($homework->isLessonUpcoming(), 404);

        $user = $request->user();

        $inProgress = Submission::where('homework_id', $homework->id)
            ->where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->latest('id')
            ->first();

        if ($inProgress) {
            return $this->redirectToNextQuestion($inProgress);
        }

        // attempts_allowed: null/0 => безлимит. Незавершённые попытки в счёт не идут.
        $rawMax      = $homework->attempts_allowed;
        $isUnlimited = empty($rawMax) || (int) $rawMax === 0;
        $attemptsMax = $isUnlimited ? null : (int) $rawMax;

        $attemptsUsed = Submission::where('user_id', $user->id)
            ->where('homework_id', $homework->id)
            ->where('status', '!=', 'in_progress')
            ->count();

        $retry = $request->boolean('retry'); // флажок из ссылки «Перерешать работу»

        if (!$retry) {
            $existing = Submission::where('homework_id', $homework->id)
                ->where('user_id', $user->id)
                ->where('status', '!=', 'in_progress')
                ->latest('id')
                ->first();

            if ($existing) {
                return redirect()->route('student.submissions.show', $existing);
            }
        }

        if (!$isUnlimited && $attemptsMax !== null && $attemptsUsed >= $attemptsMax) {
            $last = Submission::where('user_id', $user->id)
                ->where('homework_id', $homework->id)
                ->where('status', '!=', 'in_progress')
                ->latest('id')
                ->first();

            return $last
                ? redirect()->route('student.submissions.show', $last)
                    ->withErrors(['attempts' => 'Вы исчерпали лимит попыток по этой работе.'])
                : back()->withErrors(['attempts' => 'Вы исчерпали лимит попыток по этой работе.']);
        }

        $tasks = $this->orderedTasks($homework->id);
        if ($tasks->isEmpty()) {
            return back()->withErrors(['homework' => 'В этой домашней работе пока нет заданий.']);
        }

        $submission = Submission::create([
            'homework_id' => $homework->id,
            'user_id'     => $user->id,
            'attempt_no'  => $attemptsUsed + 1,
            'answers'     => [],
            'status'      => 'in_progress',
        ]);

        return redirect()->route('student.submissions.question', [$submission, 1]);
    }

    /**
     * Показ одного вопроса домашки.
     */
    public function question(Request $request, Submission $submission, int $position)
    {
        $this->assertOwner($request, $submission);
        if ($redirect = $this->ensureInProgress($request, $submission)) {
            return $redirect;
        }

        [$tasks, $task, $total] = $this->resolvePosition($submission, $position);

        return view(
            $this->view($request, 'question'),
            $this->questionData($submission, $tasks, $task, $position, $total)
        );
    }

    /**
     * Проверка ответа на авто-проверяемый вопрос.
     * Если ответ полностью верный — сразу сохраняется, и пользователь
     * переходит дальше (с тост-уведомлением). Если нет — ничего не
     * сохраняется, показывается модалка с выбором «переответить» / «дальше».
     */
    public function check(Request $request, Submission $submission, int $position)
    {
        $this->assertOwner($request, $submission);
        if ($redirect = $this->ensureInProgress($request, $submission)) {
            return $redirect;
        }

        [$tasks, $task, $total] = $this->resolvePosition($submission, $position);

        if (!$task->isAutoGradable()) {
            abort(404);
        }

        $data = $request->validate(['answer' => 'nullable|string']);
        $answer = $data['answer'] ?? null;

        $result = app(AutoGrader::class)->scoreOne($task, $answer);

        if ($result['status'] === 'ok') {
            return $this->persistAnswerAndAdvance($request, $submission, $task, $answer, $result);
        }

        return view(
            $this->view($request, 'question'),
            $this->questionData($submission, $tasks, $task, $position, $total, $result, $answer)
        );
    }

    /**
     * Окончательно сохраняет ответ на вопрос (авто- и ручные типы)
     * и переходит к следующему неотвеченному вопросу.
     */
    public function save(Request $request, Submission $submission, int $position)
    {
        $this->assertOwner($request, $submission);
        if ($redirect = $this->ensureInProgress($request, $submission)) {
            return $redirect;
        }

        [, $task] = $this->resolvePosition($submission, $position);

        $data = $request->validate(['answer' => 'nullable|string']);
        $answer = $data['answer'] ?? null;

        $result = $task->isAutoGradable() ? app(AutoGrader::class)->scoreOne($task, $answer) : null;

        return $this->persistAnswerAndAdvance($request, $submission, $task, $answer, $result);
    }

    /**
     * Сохраняет ответ на вопрос (и результат авто-проверки, если есть)
     * и переходит к следующему шагу. При полностью верном ответе
     * добавляет HX-Trigger, чтобы клиент показал тост-уведомление.
     */
    private function persistAnswerAndAdvance(Request $request, Submission $submission, HomeworkTask $task, ?string $answer, ?array $result)
    {
        $answers = $submission->answers ?? [];
        $perTask = $submission->per_task_results ?? [];

        // Корм за верный ответ — только если вопрос ещё не был отвечен:
        // без этой проверки переответ на уже сохранённый верный вопрос
        // (answers просто перезаписывается) начислял бы корм повторно.
        if ($result !== null && $result['status'] === 'ok' && !array_key_exists($task->id, $answers)) {
            app(FishFoodService::class)->awardCorrectAnswer($request->user());
        }

        $answers[$task->id] = $answer;

        if ($result !== null) {
            $perTask[$task->id] = $result;
        } else {
            unset($perTask[$task->id]); // ручные проверяет куратор — результата пока нет
        }

        $submission->answers = $answers;
        $submission->per_task_results = $perTask;
        $submission->save();

        $trigger = ($result !== null && $result['status'] === 'ok')
            ? ['toast' => ['message' => "Верно! {$result['score']} / {$result['max']} баллов"]]
            : null;

        return $this->respondNext($request, $submission, $trigger);
    }

    /**
     * Обзор перед финальной отправкой домашки.
     */
    public function finish(Request $request, Submission $submission)
    {
        $this->assertOwner($request, $submission);
        if ($redirect = $this->ensureInProgress($request, $submission)) {
            return $redirect;
        }

        $tasks = $this->orderedTasks($submission->homework_id);

        return view(
            $this->view($request, 'finish'),
            $this->finishData($submission, $tasks)
        );
    }

    /**
     * Финализация попытки: тратит attempt, считает итог — как раньше в store().
     */
    public function finishSubmit(Request $request, Submission $submission)
    {
        $this->assertOwner($request, $submission);
        if ($redirect = $this->ensureInProgress($request, $submission)) {
            return $redirect;
        }

        $tasks = $this->orderedTasks($submission->homework_id);
        $answers = $submission->answers ?? [];

        if (!$tasks->every(fn (HomeworkTask $t) => array_key_exists($t->id, $answers))) {
            if ($this->isHtmx($request)) {
                return view(
                    'student.submissions.partials.finish-region',
                    $this->finishData($submission, $tasks, 'Сначала ответьте на все вопросы.')
                );
            }

            return redirect()
                ->route('student.submissions.finish', $submission)
                ->withErrors(['submission' => 'Сначала ответьте на все вопросы.']);
        }

        $homework = Homework::find($submission->homework_id);

        $grade = app(AutoGrader::class)->gradeWithTasks($tasks, $answers);

        $submission->autocheck_score  = (int) ($grade['score'] ?? 0);
        $submission->total_score      = (int) ($grade['score'] ?? 0);
        $submission->per_task_results = $grade['per_task'] ?? null;

        $hasManual = $tasks->contains(fn (HomeworkTask $t) => in_array($t->type, HomeworkTask::MANUAL_TYPES, true));
        $submission->status = (!$hasManual && !empty($grade['fully_auto'])) ? 'checked' : 'pending';

        if (!empty($homework->due_at) && now()->isAfter($homework->due_at)) {
            $submission->status = 'expired';
        }

        $submission->save();

        // finishSubmit() гарантированно one-shot на сабмишен: ensureInProgress()
        // не пускает сюда повторно после того, как статус перестал быть
        // in_progress (выставляется чуть выше), так что доп. флаг "уже
        // начислено" не нужен — см. FishFoodService::awardHomeworkCompletion().
        app(FishFoodService::class)->awardHomeworkCompletion($request->user(), $submission);

        // Одноразовый флаг для конфетти на странице результата — не должен переживать обновление страницы.
        session()->flash('just_submitted', true);

        if ($this->isHtmx($request)) {
            return response('')->header('HX-Redirect', route('student.submissions.show', $submission));
        }

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

        if ($submission->status === 'in_progress') {
            return $this->redirectToNextQuestion($submission);
        }

        // НЕ используем Eloquent-связь homework() — берём «плоский» объект и задачи
        $hwRow = DB::table('homeworks')->where('id', $submission->homework_id)->first();
        $tasks = HomeworkTask::where('homework_id', $submission->homework_id)->get();

        // Сконструируем лёгкий объект в том же формате, который ждёт шаблон
        $homework = (object) [
            'id'    => $submission->homework_id,
            'title' => $hwRow->title ?? 'Домашняя работа',
            'tasks' => $tasks,
        ];

        // история попыток пользователя по этой работе
        $attempts = Submission::where('user_id', $submission->user_id)
            ->where('homework_id', $submission->homework_id)
            ->where('status', '!=', 'in_progress')
            ->orderByDesc('created_at')
            ->get(['id', 'attempt_no', 'autocheck_score', 'total_score', 'status', 'created_at']);

        return view('student.submissions.show', compact('submission', 'homework', 'attempts'));
    }

    /**
     * Имя вьюхи в зависимости от того, htmx-запрос это или обычная навигация:
     * htmx получает только фрагмент (#wizard-app), обычный запрос — полную страницу.
     */
    private function view(Request $request, string $page): string
    {
        return $this->isHtmx($request)
            ? "student.submissions.partials.{$page}-region"
            : "student.submissions.{$page}";
    }

    private function isHtmx(Request $request): bool
    {
        return $request->header('HX-Request') === 'true';
    }

    private function questionData(
        Submission $submission,
        $tasks,
        HomeworkTask $task,
        int $position,
        int $total,
        ?array $checkResult = null,
        ?string $checkAnswer = null
    ): array {
        $answers = $submission->answers ?? [];
        $perTask = $submission->per_task_results ?? [];

        return [
            'submission'  => $submission,
            'homework'    => Homework::find($submission->homework_id),
            'tasks'       => $tasks,
            'task'        => $task,
            'position'    => $position,
            'total'       => $total,
            'savedAnswer' => $answers[$task->id] ?? null,
            'savedResult' => $perTask[$task->id] ?? null,
            'checkResult' => $checkResult,
            'checkAnswer' => $checkAnswer,
        ];
    }

    private function finishData(Submission $submission, $tasks, ?string $error = null): array
    {
        $answers = $submission->answers ?? [];
        $perTask = $submission->per_task_results ?? [];

        return [
            'submission'  => $submission,
            'homework'    => Homework::find($submission->homework_id),
            'tasks'       => $tasks,
            'answers'     => $answers,
            'perTask'     => $perTask,
            'allAnswered' => $tasks->every(fn (HomeworkTask $t) => array_key_exists($t->id, $answers)),
            'error'       => $error,
        ];
    }

    /**
     * После сохранения ответа: обычный запрос получает редирект (fallback без JS),
     * htmx — сразу фрагмент следующего состояния + заголовок HX-Push-Url,
     * чтобы адресная строка/история браузера обновились без лишнего round-trip.
     */
    private function respondNext(Request $request, Submission $submission, ?array $trigger = null)
    {
        $tasks = $this->orderedTasks($submission->homework_id);
        $answers = $submission->answers ?? [];
        $total = $tasks->count();

        $nextPosition = null;
        foreach ($tasks as $i => $t) {
            if (!array_key_exists($t->id, $answers)) {
                $nextPosition = $i + 1;
                break;
            }
        }

        if (!$this->isHtmx($request)) {
            return $nextPosition
                ? redirect()->route('student.submissions.question', [$submission, $nextPosition])
                : redirect()->route('student.submissions.finish', $submission);
        }

        if ($nextPosition) {
            $task = $tasks[$nextPosition - 1];
            $html = view(
                'student.submissions.partials.question-region',
                $this->questionData($submission, $tasks, $task, $nextPosition, $total)
            );
            $url = route('student.submissions.question', [$submission, $nextPosition]);
        } else {
            $html = view('student.submissions.partials.finish-region', $this->finishData($submission, $tasks));
            $url = route('student.submissions.finish', $submission);
        }

        $response = response($html)->header('HX-Push-Url', $url);

        if ($trigger) {
            $response->headers->set('HX-Trigger', json_encode($trigger));
        }

        return $response;
    }

    private function orderedTasks(int $homeworkId)
    {
        return HomeworkTask::where('homework_id', $homeworkId)
            ->orderBy('order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{0: \Illuminate\Support\Collection, 1: HomeworkTask, 2: int}
     */
    private function resolvePosition(Submission $submission, int $position): array
    {
        $tasks = $this->orderedTasks($submission->homework_id);
        $total = $tasks->count();

        if ($position < 1 || $position > $total) {
            abort(404);
        }

        return [$tasks, $tasks[$position - 1], $total];
    }

    private function redirectToNextQuestion(Submission $submission)
    {
        $tasks = $this->orderedTasks($submission->homework_id);
        $answers = $submission->answers ?? [];

        foreach ($tasks as $i => $t) {
            if (!array_key_exists($t->id, $answers)) {
                return redirect()->route('student.submissions.question', [$submission, $i + 1]);
            }
        }

        return redirect()->route('student.submissions.finish', $submission);
    }

    private function assertOwner(Request $request, Submission $submission): void
    {
        if ($submission->user_id !== $request->user()->id) {
            abort(403);
        }
    }

    private function ensureInProgress(Request $request, Submission $submission)
    {
        if ($submission->status !== 'in_progress') {
            if ($this->isHtmx($request)) {
                return response('')->header('HX-Redirect', route('student.submissions.show', $submission));
            }

            return redirect()->route('student.submissions.show', $submission);
        }

        return null;
    }
}
