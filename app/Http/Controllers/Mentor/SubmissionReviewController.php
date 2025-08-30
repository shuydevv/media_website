<?php

namespace App\Http\Controllers\Mentor;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

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

        $taskKey = (string)$taskId;
        $per = $submission->per_task_results ?? [];
        if (isset($per[$taskKey]['skipped'])) {
            unset($per[$taskKey]['skipped']);
        }

        // если работа исторически в статусе 'skipped' — вернём, если пропусков больше нет
        if ($submission->status === 'skipped') {
            $stillSkipped = collect($per)->contains(fn($r) => is_array($r) && !empty($r['skipped']));
            if (!$stillSkipped) {
                $submission->status = 'submitted';
            }
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
        $this->finalizeSubmission($request, $submission);

            return redirect()->route('mentor.submissions.index')
        ->with('success', 'Проверка завершена.');
    }

    /** Завершить и перейти к следующей в очереди */
    public function finishAndNext(Request $request, Submission $submission)
    {
        $this->assertMentorOrAdmin($request);
        $this->finalizeSubmission($request, $submission);

        // здесь можешь реализовать переадресацию на следующую работу
        // пример-заглушка:
        return redirect()
            ->route('mentor.review.inbox')
            ->with('success', 'Проверка завершена. Открыта следующая работа.');
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

        // Статус
        // if ($isAdmin) {
        //     $submission->status = 'checked';
        // } else {
        //     $submission->status = $hasSkipped ? 'pending' : 'checked';
        // }

        $submission->locked_by = null;
        $submission->lock_expires_at = null;

        $submission->save();
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

        $manualTypes = ['written','image_written','image_manual'];
        $auto   = $tasks->filter(fn($t) => !in_array(($t->type ?? ''), $manualTypes, true));
        $manual = $tasks->filter(fn($t) =>  in_array(($t->type ?? ''), $manualTypes, true));

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
        $list = collect(is_array($tasks) ? $tasks : []);
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
