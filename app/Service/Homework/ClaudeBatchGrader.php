<?php

namespace App\Service\Homework;

use App\Models\AiGradingBatch;
use App\Models\HomeworkTask;
use App\Models\Submission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * «Проверить разом»: все ещё не проверенные письменные задания из очереди
 * куратора уходят одной пачкой в Claude Batch API — это вдвое дешевле
 * поштучных запросов, но результат приходит не сразу (обычно в пределах
 * часа, максимум 24 часа). Пока пачка обрабатывается, у задания в
 * ai_drafts лежит маркер status=pending; результаты раскладывает
 * collect() — его раз в 5 минут вызывает ai:collect-grading-batches.
 *
 * Запрос и разбор ответа — те же, что у поштучной проверки (ClaudeGrader),
 * поэтому черновик из пачки ничем не отличается для куратора.
 */
class ClaudeBatchGrader
{
    /** Чтобы случайный клик не отправил тысячи заданий разом. */
    private const MAX_PER_BATCH = 500;

    public function __construct(private ClaudeGrader $grader, private AiGradingQueue $queue)
    {
    }

    /**
     * Задания, которые уйдут в пачку — см. AiGradingQueue.
     *
     * @return Collection<int, array{0: Submission, 1: HomeworkTask}>
     */
    public function candidates(?Collection $queue = null): Collection
    {
        return $this->queue->tasks($queue)->take(self::MAX_PER_BATCH)->values();
    }

    /** Отправить пачку. null — проверять нечего. */
    public function submit(?int $userId, ?Collection $queue = null): ?AiGradingBatch
    {
        if (!$this->grader->isEnabled()) {
            throw new RuntimeException('ИИ-проверка не настроена: нет ANTHROPIC_API_KEY.');
        }

        $items = $this->candidates($queue);
        if ($items->isEmpty()) {
            return null;
        }

        $requests = [];
        $pending = [];
        foreach ($items as [$s, $t]) {
            $payload = $this->grader->payloadFor($s, $t);
            $requests[] = [
                'customId' => "s{$s->id}-t{$t->id}",
                'params'   => $this->grader->requestParams($payload),
            ];
            $pending[$s->id][(string) $t->id] = (int) $payload['max_score'];
        }

        $batch = $this->grader->client()->messages->batches->create(requests: $requests);

        $record = AiGradingBatch::create([
            'channel'            => AiGradingBatch::CHANNEL_API,
            'external_id'        => $batch->id,
            'status'             => 'in_progress',
            'request_count'      => count($requests),
            'created_by'         => $userId,
        ]);

        foreach ($pending as $submissionId => $tasks) {
            $this->updateDrafts($submissionId, function (array $drafts) use ($tasks, $record) {
                foreach ($tasks as $key => $maxScore) {
                    $drafts[$key] = ['status' => 'pending', 'batch' => $record->id, 'max_score' => $maxScore];
                }
                return $drafts;
            });
        }

        Log::info('ClaudeBatchGrader submitted', ['batch' => $batch->id, 'requests' => count($requests)]);

        return $record;
    }

    /** Забрать результаты всех завершившихся пачек. Возвращает число обработанных пачек. */
    public function collect(): int
    {
        $done = 0;
        foreach (AiGradingBatch::where('channel', AiGradingBatch::CHANNEL_API)->where('status', 'in_progress')->get() as $record) {
            $batches = $this->grader->client()->messages->batches;
            if ($batches->retrieve($record->external_id)->processingStatus !== 'ended') {
                continue;
            }

            // Результаты приходят в произвольном порядке — раскладываем по customId.
            $bySubmission = [];
            foreach ($batches->resultsStream($record->external_id) as $item) {
                if (preg_match('/^s(\d+)-t(\d+)$/', $item->customID, $m)) {
                    $bySubmission[(int) $m[1]][$m[2]] = $item->result;
                }
            }

            $ok = 0;
            $failed = 0;
            foreach ($bySubmission as $submissionId => $results) {
                $this->updateDrafts($submissionId, function (array $drafts) use ($results, $record, &$ok, &$failed) {
                    foreach ($results as $key => $result) {
                        $current = $drafts[$key] ?? [];
                        // Куратор мог уже перепроверить задание поштучно —
                        // тогда его черновик новее, не затираем.
                        if (($current['status'] ?? null) !== 'pending' || ($current['batch'] ?? null) !== $record->id) {
                            continue;
                        }
                        try {
                            if ($result->type !== 'succeeded') {
                                throw new RuntimeException(match ($result->type) {
                                    'expired' => 'Пачка не успела обработаться за 24 часа.',
                                    'canceled' => 'Проверка отменена.',
                                    default => 'Ошибка API при проверке.',
                                });
                            }
                            $drafts[$key] = $this->grader->parse($result->message, (int) ($current['max_score'] ?? 0));
                            $ok++;
                        } catch (\Throwable $e) {
                            $drafts[$key] = ['status' => 'error', 'error' => $e->getMessage()];
                            $failed++;
                        }
                    }
                    return $drafts;
                });
            }

            $record->update([
                'status'          => 'ended',
                'ended_at'        => now(),
                'succeeded_count' => $ok,
                'failed_count'    => $failed,
            ]);
            Log::info('ClaudeBatchGrader collected', ['batch' => $record->external_id, 'ok' => $ok, 'failed' => $failed]);
            $done++;
        }

        return $done;
    }

    /** Перечитать работу под блокировкой: параллельно куратор может сохранять оценки. */
    private function updateDrafts(int $submissionId, callable $mutate): void
    {
        DB::transaction(function () use ($submissionId, $mutate) {
            $s = Submission::whereKey($submissionId)->lockForUpdate()->first();
            if (!$s) {
                return;
            }
            $s->ai_drafts = $mutate($s->ai_drafts ?? []);
            $s->save();
        });
    }
}
