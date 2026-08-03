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

        // Незавершённые попытки в счёт не идут. Дефолт и единственный
        // источник истины для "сколько попыток разрешено" — Homework::
        // attemptsAllowed() (используется и здесь, и на странице результатов).
        $attemptsMax = $homework->attemptsAllowed();

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

        if ($attemptsUsed >= $attemptsMax) {
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

        $attrs = [
            'homework_id' => $homework->id,
            'user_id'     => $user->id,
            'attempt_no'  => $attemptsUsed + 1,
            'answers'     => [],
            'status'      => 'in_progress',
        ];

        // Пробник — фиксированный таймер на прохождение (3ч30м, см.
        // Homework::MOCK_TIME_LIMIT_MINUTES). Проверяется реактивно, см.
        // autoFinishIfExpired().
        if ($homework->type === 'mock') {
            $attrs['started_at'] = now();
            $attrs['expires_at'] = now()->addMinutes(Homework::MOCK_TIME_LIMIT_MINUTES);
        }

        try {
            $submission = Submission::create($attrs);
        } catch (\Illuminate\Database\QueryException $e) {
            // Гонка: параллельный запрос (двойной клик, две вкладки) успел
            // создать in_progress-попытку первым — уникальный индекс
            // submissions_one_in_progress_unique (см. миграцию) не даёт
            // вставить вторую. Не 500, а просто уходим к уже созданной попытке.
            if ((string) $e->getCode() !== '23000') {
                throw $e;
            }

            $inProgress = Submission::where('homework_id', $homework->id)
                ->where('user_id', $user->id)
                ->where('status', 'in_progress')
                ->latest('id')
                ->first();

            if ($inProgress) {
                return $this->redirectToNextQuestion($inProgress);
            }

            throw $e;
        }

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
            $this->questionData($request, $submission, $tasks, $task, $position, $total)
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

        // Пробник: результат проверки студент не должен видеть до отправки
        // всей работы — ни модалки «Верно/Неверно», ни блокировки поля.
        // Балл всё равно считается и сохраняется в per_task_results (нужен
        // для finalize() и итоговой страницы результатов), просто не
        // показывается сейчас. Проверяем на контроллере, а не только на
        // фронтенде — иначе прямой POST на эту ручку в обход формы вскрыл бы
        // правильность ответа раньше времени.
        $homework = Homework::find($submission->homework_id);
        if (($homework->type ?? null) === 'mock') {
            return $this->persistAnswerAndAdvance($request, $submission, $task, $answer, $result);
        }

        if ($result['status'] === 'ok') {
            return $this->persistAnswerAndAdvance($request, $submission, $task, $answer, $result);
        }

        return view(
            $this->view($request, 'question'),
            $this->questionData($request, $submission, $tasks, $task, $position, $total, $result, $answer)
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
        $fish = app(FishFoodService::class);

        $answers[$task->id] = $answer;

        if ($result !== null) {
            // Корм за задание = балл за задание (см. FishFoodService::
            // syncTaskCorm()) — переносим уже начисленное с прошлой попытки
            // ответить на этот же вопрос, иначе дельта считалась бы от нуля
            // и корм задваивался бы при переответе.
            $result['fish_awarded'] = $perTask[$task->id]['fish_awarded'] ?? 0;
            $perTask[$task->id] = $result;
        } else {
            unset($perTask[$task->id]); // ручные проверяет куратор — результата пока нет
        }

        // Начисление корма (пишет в users, InnoDB) и сохранение ответа
        // (submissions, MyISAM) — в одной транзакции: если save() не
        // пройдёт, откатывается и уже начисленный корм. Без этого при сбое
        // между двумя операциями fish_awarded оставался бы несохранённым
        // (per_task_results не записался), и следующая попытка ответить на
        // тот же вопрос начислила бы корм за него ещё раз поверх уже
        // списанного в БД баланса.
        DB::transaction(function () use (&$perTask, $task, $result, $fish, $request, $submission, $answers) {
            if ($result !== null) {
                $fish->syncTaskCorm($request->user(), $perTask[$task->id]);
            }

            $submission->answers = $answers;
            $submission->per_task_results = $perTask;
            $submission->save();
        });

        // Пробник: как и модалка "Верно/Неверно" в check() выше, тост тоже
        // не должен показываться — иначе результат проверки всё равно
        // утекал бы студенту раньше срока, просто другим способом.
        $isMock = ($submission->homework?->type ?? null) === 'mock';

        $trigger = ($result !== null && $result['status'] === 'ok' && !$isMock)
            ? ['toast' => [
                'message' => "Верно! {$result['score']} / {$result['max']} баллов",
                'icon'    => $fish->mascotImageUrl($fish->levelFor((int) $request->user()->fish_total_fed), 'correct'),
            ]]
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

        // Неотвеченные вопросы разрешены: студент может отправить работу на
        // проверку не решив всё до конца — незаполненные задания просто
        // получат 0 (AutoGrader уже это умеет — см. scoreAuto(null, ...)),
        // а неотвеченные ручные останутся без per_task_results до куратора,
        // как и любой другой неотвеченный ручной вопрос.
        $tasks = $this->orderedTasks($submission->homework_id);

        $homework = Homework::find($submission->homework_id);

        $this->finalize($submission, $homework);

        // Одноразовый флаг для конфетти на странице результата — не должен переживать обновление страницы.
        session()->flash('just_submitted', true);

        if ($this->isHtmx($request)) {
            return response('')->header('HX-Redirect', route('student.submissions.show', $submission));
        }

        return redirect()
            ->route('student.submissions.show', $submission)
            ->with('success', 'Ответ отправлен');
    }

    /**
     * Считает итог по попытке и переводит статус — общая логика для явной
     * отправки (finishSubmit()) и для реактивного авто-завершения по таймеру
     * (autoFinishIfExpired()). Берёт пользователя из $submission->user, а не
     * из текущего Request — метод должен одинаково работать в обоих случаях
     * (при авто-завершении текущий запрос может быть от кого угодно/без
     * пользователя, например следующий заход владельца после истечения времени).
     */
    private function finalize(Submission $submission, Homework $homework): void
    {
        // submissions — таблица MyISAM (см. миграцию add_in_progress_guard):
        // DB::transaction()/lockForUpdate() тут не дают настоящей
        // атомарности (MyISAM их не поддерживает), поэтому гонку двух
        // параллельных вызовов finalize() для одной попытки (двойной клик по
        // «Завершить», столкновение ручной отправки с реактивным
        // авто-финишем пробника по таймеру в ensureInProgress()) закрываем
        // атомарным UPDATE ... WHERE status = 'in_progress': MyISAM
        // сериализует конкурентные UPDATE табличной блокировкой, так что
        // только один из двух запросов реально меняет строку и получает
        // affected=1, проигравший видит 0 и выходит без повторного
        // начисления корма за домашку.
        $claimed = Submission::where('id', $submission->id)
            ->where('status', 'in_progress')
            ->update(['status' => 'grading']);

        if ($claimed === 0) {
            $submission->refresh();
            return;
        }

        $submission->refresh();

        $tasks = $this->orderedTasks($submission->homework_id);
        $answers = $submission->answers ?? [];

        $grade = app(AutoGrader::class)->gradeWithTasks($tasks, $answers);
        $perTask = $grade['per_task'] ?? [];
        $autoScore = (int) ($grade['score'] ?? 0);

        // Пустой ответ на ручное задание (или вопрос вовсе оставлен без
        // ответа — теперь так можно, см. finishSubmit()) сразу закрывается
        // нулём — куратору тут нечего проверять. Формат тот же, что и у
        // настоящей проверки куратора (per_task_results[$id]['score']),
        // поэтому дальше по коду (allManualTasksClosedForMentor/Admin,
        // страница результата) такое задание выглядит как уже закрытое.
        $manualScore = 0;
        $hasPendingManual = false;

        foreach ($tasks as $t) {
            if ($t->isAutoGradable()) {
                continue;
            }

            $tid = $t->id;
            $answer = $answers[$tid] ?? null;

            if (trim((string) $answer) === '') {
                $perTask[$tid]['score'] = 0;
                unset($perTask[$tid]['skipped']);
            } else {
                $hasPendingManual = true;
                continue;
            }

            $manualScore += (int) $perTask[$tid]['score'];
        }

        $submission->autocheck_score  = $autoScore;
        $submission->per_task_results = $perTask;

        if ($hasPendingManual) {
            // Есть хотя бы одно непустое ручное задание — это по-прежнему
            // уходит куратору, total_score до его решения не финальный
            // (та же логика, что и раньше).
            $submission->total_score = $autoScore;
            $submission->status = 'pending';
        } else {
            // Ручных заданий либо нет вовсе, либо все пустые и уже закрыты
            // нулём выше — куратору отправлять нечего, можно сразу "Проверено".
            $submission->manual_score = $manualScore;
            $submission->total_score  = $autoScore + $manualScore;
            $submission->status = 'checked';
        }

        if ($homework->isOverdueFor($submission->user)) {
            $submission->status = 'expired';
        }

        $submission->save();

        // Аналогично finishSubmit() — one-shot: ensureInProgress()/
        // autoFinishIfExpired() не пускают сюда повторно после того, как
        // статус перестал быть in_progress (выставляется чуть выше).
        app(FishFoodService::class)->awardHomeworkCompletion($submission->user, $submission);
    }

    /**
     * Реактивная проверка таймера пробника — вызывается из ensureInProgress()
     * перед каждым обращением к visard'у (question/check/save/finish), по
     * той же схеме, что и проверка due_at: без фоновых джобов, попытка
     * закрывается по факту следующего обращения после истечения времени.
     * Неотвеченные вопросы получают 0 — уже существующая обработка
     * answer === null в AutoGrader::scoreAuto(), доп. проверки не нужны.
     */
    private function autoFinishIfExpired(Submission $submission): void
    {
        if ($submission->status !== 'in_progress' || !$submission->isExpired()) {
            return;
        }

        $homework = Homework::find($submission->homework_id);
        $this->finalize($submission, $homework);
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

        // Сконструируем лёгкий объект в том же формате, который ждёт шаблон.
        // attempts_allowed нормализуем тем же правилом, что и в create()
        // (Homework::normalizeAttemptsAllowed) — раньше шаблон сам считал
        // дефолт по-другому (?? 2, не ловит явный 0), из-за чего "Перерешать
        // работу" могла быть недоступна, хотя лимит ещё не исчерпан.
        $homework = (object) [
            'id'    => $submission->homework_id,
            'title' => $hwRow->title ?? 'Домашняя работа',
            'tasks' => $tasks,
            'attempts_allowed' => Homework::normalizeAttemptsAllowed($hwRow->attempts_allowed ?? null),
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
        Request $request,
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
            'fishLevel'   => app(FishFoodService::class)->levelFor((int) $request->user()->fish_total_fed),
            'expiresAt'   => $submission->expires_at?->toIso8601String(),
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
            'expiresAt'   => $submission->expires_at?->toIso8601String(),
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
                $this->questionData($request, $submission, $tasks, $task, $nextPosition, $total)
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
        $this->autoFinishIfExpired($submission);

        if ($submission->status !== 'in_progress') {
            if ($this->isHtmx($request)) {
                return response('')->header('HX-Redirect', route('student.submissions.show', $submission));
            }

            return redirect()->route('student.submissions.show', $submission);
        }

        return null;
    }
}
