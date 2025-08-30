<?php

namespace App\Http\Controllers\Mentor;

use App\Models\Task;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReviewAiController extends Controller
{
    public function regen(Request $request, Submission $submission, string $taskId)
    {
        try {
            // answers / tasks
            $answers   = $submission->answers ?? [];
            $homework  = $submission->homework;
            $tasksRaw  = $homework->tasks ?? [];

            if (is_string($tasksRaw)) {
                $decoded = json_decode($tasksRaw, true);
                $tasksRaw = is_array($decoded) ? $decoded : [];
            }

            // найти описание нужного задания по id
            $task = null;
            foreach ($tasksRaw as $t) {
                $tid = is_array($t) ? ($t['id'] ?? null) : (is_object($t) ? ($t->id ?? null) : null);
                if ((string)$tid === (string)$taskId) { $task = is_array($t) ? (object)$t : (object)($t ?? []); break; }
            }
            // если не нашли, создадим заглушку (например, когда id — псевдо "t_manual_0")
            if (!$task) $task = (object)[ 'id'=>$taskId, 'max_score'=>3 ];

        $studentAnswer = (string)($answers[$taskId] ?? '');
        $criteria      = (string)($task->criteria
                            ?? $task->rubric
                            ?? $task->rules
                            ?? '');
        $exemplar      = (string)($task->exemplar
                            ?? $task->sample_answer
                            ?? $task->solution
                            ?? '');
        $maxScore      = (int)   ($task->max_score ?? 3);

        // Поддержка разных названий "Комментария" в Task
        $commentHint   = (string)($task->comment
                            ?? $task->mentor_comment
                            ?? $task->review_comment
                            ?? $task->notes
                            ?? $task->tips
                            ?? '');

        // Если в JSON-задаче комментария нет — попробуем достать из библиотеки по task_id
        if (trim($commentHint) === '') {
            $libTaskId = (int)($task->task_id ?? 0);
            if ($libTaskId > 0) {
                if ($lib = Task::query()->find($libTaskId)) {
                    // заполним comment и заодно подстрахуем criteria/exemplar, если они пустые
                    $commentHint = (string)($lib->comment ?? '');
                    if ($criteria === '') {
                        $criteria = (string)($lib->criteria ?? '');
                    }
                    // если хочешь, можно тянуть и шаблон как exemplar:
                    // if ($exemplar === '' && !empty($lib->ai_rationale_template)) {
                    //     $exemplar = (string)$lib->ai_rationale_template;
                    // }
                }
            }
        }

        // Лог для проверки, что комментарий реально передаётся
        Log::debug('AI regen IN', [
            'taskId'          => $taskId,
            'has_comment'     => trim($commentHint) !== '',
            'comment_snippet' => mb_substr($commentHint, 0, 160),
        ]);

        // вызов сервиса
        $ai = app(\App\Service\OpenAIService::class);
        $raw = $ai->draftScore(
            studentAnswer: $studentAnswer,
            criteria:      $criteria,
            maxScore:      $maxScore,
            exemplar:      $exemplar,
            comment:       $commentHint
        );


            // нормализуем ключи на всякий
            $draft = [
                'score'          => (int)($raw['score'] ?? 0),
                'rationale'      => (string)($raw['rationale'] ?? $raw['explanation'] ?? ''),
                'comment'        => (string)($raw['comment']   ?? $raw['recommendation'] ?? ''),
                // алиасы, чтобы старые шаблоны тоже видели
                'explanation'    => (string)($raw['rationale'] ?? $raw['explanation'] ?? ''),
                'recommendation' => (string)($raw['comment']   ?? $raw['recommendation'] ?? ''),
            ];

            // сохранить в submissions.ai_drafts[taskId]
            $drafts = $submission->ai_drafts ?? [];
            $drafts[$taskId] = $draft;
            $submission->ai_drafts = $drafts;
            $submission->save();

            // dd($task);

            // вернуть JSON для фронта
            return response()->json([
                'ok'        => true,
                'taskId'    => $taskId,
                'score'     => $draft['score'],
                'rationale' => $draft['rationale'],
                'comment'   => $draft['comment'],
            ]);
        } catch (\Throwable $e) {
            Log::error('AI exception', ['message' => $e->getMessage()]);
            if (app()->environment('local')) {
                $msg = $e->getMessage();
                // Явно покажем таймаут, чтобы не путать с “пустыми полями”
                return [
                    'score'          => 0,
                    'rationale'      => 'DEV: API error — ' . $msg,
                    'comment'        => 'DEV: проверь VPN/сеть, модель, ключ и SSL. Увеличен timeout и поставлен retry.',
                    'explanation'    => 'DEV: API error — ' . $msg,
                    'recommendation' => 'DEV: проверь VPN/сеть, модель, ключ и SSL. Увеличен timeout и поставлен retry.',
                ];
            }
            throw $e;
        }
    }
}
