<?php

namespace App\Http\Controllers\Mentor;

use App\Http\Controllers\Controller;
use App\Models\HomeworkTask;
use App\Models\Submission;
use App\Models\User;
use App\Service\FishFoodService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

use App\Notifications\HomeworkGradedNotification;

class SubmissionReviewController extends Controller
{
    /** Проверка прав простая: ментор или админ */
    private function assertMentorOrAdmin(Request $request): void
    {
        $u = $request->user();
        abort_unless(
            $u && \in_array((int)$u->role, [User::ROLE_MENTOR, User::ROLE_ADMIN], true),
            403,
            'Доступ только для куратора или администратора.'
        );
    }

    /**
     * Маршрут mentor.review.inbox ссылался на этот метод, которого не
     * существовало вовсе (BadMethodCallException при любом заходе) — сам
     * маршрут нигде в интерфейсе не используется как ссылка, но был живой
     * миной на случай прямого перехода/закладки. Рабочая версия очереди —
     * mentor.submissions.index, туда и отправляем.
     */
    public function inbox(Request $request)
    {
        $this->assertMentorOrAdmin($request);

        return redirect()->route('mentor.submissions.index');
    }

    /** Просмотр страницы проверки */
    public function show(Request $request, Submission $submission)
    {
        $this->assertMentorOrAdmin($request);

        // по желанию продлеваем lock текущему владельцу
        $u = $request->user();
        if ($submission->locked_by === $u->id) {
            $submission->lock_expires_at = now()->addHour();
            $submission->save();
        }

        return view('mentor.review.show', [
            'submission' => $submission->loadMissing(['user','homework.lesson.courseSession.course']),
        ]);
    }

    /** Сохранить оценку / обоснование / комментарий по конкретной задаче */
    public function saveTask(Request $request, Submission $submission, string $taskId)
    {
        Log::info('HIT saveTask', ['route' => Route::currentRouteName(), 'taskId' => $taskId]);
        $this->assertMentorOrAdmin($request);
        // Роли "ментор/админ" мало — без этого любой куратор мог писать
        // оценки в чужую активно залоченную работу, пока её проверяет
        // другой куратор (see SubmissionPolicy::update — учитывает locked_by).
        $this->authorize('update', $submission);

        $data = $request->validate([
            'score'   => ['nullable','integer','min:0'],
            'reason'  => ['nullable','string'],
            'comment' => ['nullable','string'],
        ]);

        // Гарантируем, что ключ — строка
        $taskKey = (string)$taskId;

        $per = $submission->per_task_results ?? [];
        $row = isset($per[$taskKey]) && is_array($per[$taskKey]) ? $per[$taskKey] : [];

        // max_score берём из описания домашки
        $tasks = $submission->homework->tasks ?? [];
        $maxScore = $this->resolveTaskMaxScore($tasks, $taskKey);

        // нормализуем оценку и клампим
        if (Arr::exists($data, 'score') && $data['score'] !== null) {
            $score = (int)$data['score'];
            if ($maxScore !== null) {
                $score = max(0, min($score, (int)$maxScore));
            } else {
                $score = max(0, $score);
            }
            $row['score'] = $score;
        }

        if (Arr::exists($data, 'reason'))  { $row['reason']  = $data['reason'];  }
        if (Arr::exists($data, 'comment')) { $row['comment'] = $data['comment']; }

        // Любое явное сохранение снимает флаг пропуска:
        unset($row['skipped']);

        $per[$taskKey] = $row;
        $submission->per_task_results = $per;

        // если куратор держит лок — продлим
        $u = $request->user();
        if ($submission->locked_by === $u->id) {
            $submission->lock_expires_at = now()->addHour();
        }

        $submission->save();

        return back()->with('success', 'Задание сохранено');
    }

    /** Пометить задачу как пропущенную (эскалация администратору) */
    public function skipTask(Request $request, Submission $submission, string $taskId)
    {
        Log::info('HIT skipTask', ['route' => Route::currentRouteName(), 'taskId' => $taskId]);
        $this->assertMentorOrAdmin($request);
        $this->authorize('update', $submission);

        $taskKey = (string)$taskId;
        $per = $submission->per_task_results ?? [];
        $row = isset($per[$taskKey]) && is_array($per[$taskKey]) ? $per[$taskKey] : [];
        $row['skipped'] = true;
        $per[$taskKey] = $row;

        $submission->per_task_results = $per;

         $submission->status = 'pending';

        // ВАЖНО: здесь НЕ меняем общий статус работы.
        // Общий статус выставляется в finish()/finishAndNext().
        $u = $request->user();
        if ($submission->locked_by === $u->id) {
            $submission->lock_expires_at = now()->addHour();
        }

        $submission->save();

        return back()->with('warning', 'Задание помечено как пропущенное');
    }

    /** Вернуть задачу из «пропущено» обратно куратору */
    public function unskipTask(Request $request, Submission $submission, string $taskId)
    {
         Log::info('HIT unskipTask', ['route' => Route::currentRouteName(), 'taskId' => $taskId]);
        $this->assertMentorOrAdmin($request);
        $this->authorize('update', $submission);

        $taskKey = (string)$taskId;
        $per = $submission->per_task_results ?? [];
        if (isset($per[$taskKey]['skipped'])) {
            unset($per[$taskKey]['skipped']);
        }

        $submission->per_task_results = $per;

        $u = $request->user();
        if ($submission->locked_by === $u->id) {
            $submission->lock_expires_at = now()->addHour();
        }

        $submission->save();

        return back()->with('success', 'Задание возвращено на проверку');
    }

    /** Завершить проверку текущей работы */
    public function finish(Request $request, Submission $submission)
    {
        $this->assertMentorOrAdmin($request);
        $this->authorize('update', $submission);
        $this->finalizeSubmission($request, $submission);

            return redirect()->route('mentor.submissions.index')
        ->with('success', 'Проверка завершена.');
    }

    /** Завершить и перейти к следующей в очереди */
    public function finishAndNext(Request $request, Submission $submission)
    {
        $this->assertMentorOrAdmin($request);
        $this->authorize('update', $submission);
        $this->finalizeSubmission($request, $submission);

        // Очередь — та же логика, что и в MentorSubmissionController::index():
        // не залоченные (либо лок истёк) работы в статусе pending, старые впереди.
        $next = Submission::query()
            ->where('id', '!=', $submission->id)
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('locked_by')
                    ->orWhereNull('lock_expires_at')
                    ->orWhere('lock_expires_at', '<=', now());
            })
            ->orderBy('created_at')
            ->first();

        if ($next) {
            // 'mentor.review.show' рендерит несуществующий view (реальная
            // страница проверки лежит по 'mentor.submissions.show' — см.
            // Mentor\SubmissionController::show(), она же и берёт лок на
            // работу). Раньше редирект сюда гарантированно падал 500 у
            // любого куратора, кто нажимал «Завершить и следующая», когда в
            // очереди ещё что-то оставалось — то есть в основном сценарии.
            return redirect()
                ->route('mentor.submissions.show', $next)
                ->with('success', 'Проверка завершена. Открыта следующая работа.');
        }

        return redirect()
            ->route('mentor.submissions.index')
            ->with('success', 'Проверка завершена. Очередь пуста.');
    }

    /* ==========================
     *           Вспомогательные
     * ========================== */

    /**
     * Выставление общего статуса работы по итогам проверки.
     * - У админа: всегда checked.
     * - У ментора: если есть пропуски => pending (уйдёт администратору), иначе checked.
     * Также можно пересчитать total_score при необходимости.
     */
    private function finalizeSubmission(Request $request, Submission $submission): void
    {
        // submissions — таблица MyISAM (нет lockForUpdate()/транзакций), а
        // finalizeSubmission() в отличие от студенческого finalize()
        // легитимно вызывается повторно (админ доскорил задание, пропущенное
        // ментором, и снова жмёт «Завершить») — простой claim по статусу
        // тут не годится. Серилизуем именованным серверным локом MySQL: не
        // зависит от движка таблицы и не мешает повторному вызову, только не
        // даёт двум запросам на одной попытке выполниться одновременно
        // (двойной клик по «Завершить», либо ментор и админ жмут «Завершить»
        // на одной работе одновременно — Policy пускает обоих, лок это не
        // проверяет для админа).
        $lockKey = 'submission_finalize_' . $submission->id;
        $gotLock = (int) (DB::selectOne('SELECT GET_LOCK(?, 5) AS got', [$lockKey])->got ?? 0);

        if (!$gotLock) {
            return;
        }

        try {
            $submission->refresh();
            $this->finalizeSubmissionLocked($request, $submission);
        } finally {
            DB::statement('SELECT RELEASE_LOCK(?)', [$lockKey]);
        }
    }

    private function finalizeSubmissionLocked(Request $request, Submission $submission): void
    {
        $u = $request->user();
        $isAdmin = (int)$u->role === User::ROLE_ADMIN;

        $per = $submission->per_task_results ?? [];
        $hasSkipped = collect($per)->contains(fn($r) => is_array($r) && !empty($r['skipped']));

        // Если есть хотя бы одно пропущенное — ВСЕГДА pending (уйдёт админу)
        if ($hasSkipped) {
            $submission->status = 'pending';
        } else {
            $submission->status = 'checked';
        }

        // Пересчёт итоговых баллов (если у тебя это где-то в сервисе — вызови его)
        [$autoScore, $manualScore, $totalMax] = $this->recalculateScores($submission);
        $submission->autocheck_score = $autoScore;
        $submission->manual_score    = $manualScore;
        $submission->total_score     = $autoScore + $manualScore;

        // Корм за ручные задания начисляется только сейчас, при завершении
        // проверки — до этого момента оценка куратора ещё не финальна (см.
        // saveTask(), там можно править балл сколько угодно раз). Идемпотентно
        // и на повторный finalize (админ донормировал очередь после пропусков
        // ментора) — см. FishFoodService::syncTaskCorm().
        $this->awardManualTaskCorm($submission);

        // Статус
        // if ($isAdmin) {
        //     $submission->status = 'checked';
        // } else {
        //     $submission->status = $hasSkipped ? 'pending' : 'checked';
        // }

        $submission->locked_by = null;
        $submission->lock_expires_at = null;

        $submission->save();

        // Уведомление ученику (в БД + письмо, см. HomeworkGradedNotification)
        // после сохранения итогов
        try {
            $student = $submission->user; // связь уже подгружается в show(), но здесь подстрахуемся
            if (!$student->relationLoaded('user')) {
                $submission->loadMissing('user');
                $student = $submission->user;
            }

            if ($student && filter_var($student->email, FILTER_VALIDATE_EMAIL)) {
                $student->notify(new HomeworkGradedNotification($submission, $request->user()->name ?? null));
            }
        } catch (\Throwable $e) {
            Log::warning('Не удалось отправить уведомление о проверке', [
                'submission_id' => $submission->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Корм за ручные задания = баллы, выставленные куратором (см. FishFoodService::
     * syncTaskCorm()) — идёт по тем же строкам per_task_results, что и
     * recalculateScores(), но независимо от неё: чтобы поддерживать инвариант
     * «корм = балл» и на пересдачу очереди (skipped-задание админ доскорил
     * позже, или админ поправил оценку ментора), а не только на самое первое
     * завершение проверки. Пропущенные (skipped) задания — без оценки, корм
     * им не положен, пока их явно не доскорят.
     */
    private function awardManualTaskCorm(Submission $submission): void
    {
        $tasksRaw = $submission->homework->tasks ?? [];
        if (is_string($tasksRaw)) {
            $decoded = json_decode($tasksRaw, true);
            $tasksRaw = is_array($decoded) ? $decoded : [];
        }

        $manualTasks = collect($tasksRaw)
            ->map(fn ($t) => (object) $t)
            ->filter(fn ($t) => in_array($t->type ?? '', HomeworkTask::MANUAL_TYPES, true));

        if ($manualTasks->isEmpty()) {
            return;
        }

        $per = $submission->per_task_results ?? [];
        $fish = app(FishFoodService::class);
        $user = $submission->user;

        foreach ($manualTasks as $i => $t) {
            $tid = (string) ($t->id ?? $t->task_id ?? "t_manual_$i");
            $row = $per[$tid] ?? null;

            if (!is_array($row) || !empty($row['skipped']) || !Arr::exists($row, 'score')) {
                continue;
            }

            $fish->syncTaskCorm($user, $row);
            $per[$tid] = $row;
        }

        $submission->per_task_results = $per;
    }

    /**
     * Возвращает [autoScore, manualScore, totalMax] — простая реализация.
     * При необходимости замени на вызов твоего сервиса подсчёта.
     */
    private function recalculateScores(Submission $submission): array
    {
        $tasksRaw = $submission->homework->tasks ?? [];
        if (is_string($tasksRaw)) {
            $decoded = json_decode($tasksRaw, true);
            $tasksRaw = is_array($decoded) ? $decoded : [];
        }
        $tasks = collect($tasksRaw)->map(fn($t) => (object)$t);

        $auto   = $tasks->filter(fn($t) => !in_array(($t->type ?? ''), HomeworkTask::MANUAL_TYPES, true));
        $manual = $tasks->filter(fn($t) =>  in_array(($t->type ?? ''), HomeworkTask::MANUAL_TYPES, true));

        $per = $submission->per_task_results ?? [];

        $sumScores = function ($coll) use ($per) {
            $s = 0;
            foreach ($coll as $i => $t) {
                $tid = (string)($t->id ?? $t->task_id ?? "t_$i");
                $max = (int)($t->max_score ?? 1);
                $row = $per[$tid] ?? [];
                $sc  = (int)($row['score'] ?? 0);
                // кламп на всякий случай
                $s  += max(0, min($sc, $max));
            }
            return $s;
        };

        $autoScore   = $sumScores($auto);
        $manualScore = $sumScores($manual);
        $totalMax    = (int)$tasks->sum(fn($t) => (int)($t->max_score ?? 1));

        return [$autoScore, $manualScore, $totalMax];
    }

    /**
     * Поиск max_score для конкретной задачи по её ключу.
     * Ключи у нас бывают: реальный id/ task_id (число) или «t_manual_3».
     */
    private function resolveTaskMaxScore($tasks, string $taskKey): ?int
    {
        // Раньше здесь стояло collect(is_array($tasks) ? $tasks : []) — но
        // $submission->homework->tasks (см. вызов в saveTask()) это Eloquent-
        // коллекция HomeworkTask, а не array, is_array() на ней всегда false.
        // Метод молча получал пустой список и ВСЕГДА возвращал null —
        // saveTask() из-за этого никогда не клампил присланный курато­ром
        // балл к max_score задания (наружу это не было видно: итоговый
        // total_score в recalculateScores() клампится отдельно и правильно,
        // но per_task_results[$taskKey]['score'] — и, что важнее, корм,
        // который FishFoodService::syncTaskCorm() начисляет прямо по этому
        // полю в awardManualTaskCorm() — сохранялся и начислялся без
        // ограничения). collect() сам умеет и в массив, и в Collection.
        $list = collect($tasks);
        // Если ключ числовой — проще
        if (ctype_digit($taskKey)) {
            $id = (int)$taskKey;
            $obj = $list->first(function ($t) use ($id) {
                $t = (object)$t;
                return (int)($t->id ?? $t->task_id ?? -1) === $id;
            });
            return $obj ? (int)((object)$obj)->max_score ?? 1 : null;
        }

        // Нечисловой ключ (fallback t_manual_i/t_auto_i) — пытаемся взять по индексу
        if (preg_match('/_(\d+)$/', $taskKey, $m)) {
            $i = (int)$m[1];
            $t = $list->get($i);
            if ($t) return (int)((object)$t)->max_score ?? 1;
        }
        return null;
    }
}
