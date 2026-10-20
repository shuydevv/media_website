<?php

namespace App\Service\Homework;

use App\Models\HomeworkTask;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Какие письменные задания ждут ИИ-проверки — общий отбор для выгрузки в
 * Claude Desktop (DesktopGradingExchange) и пачки в Claude API
 * (ClaudeBatchGrader).
 *
 * Берутся работы из очереди куратора (Submission::pendingReviewQueue():
 * не открытые сейчас у другого куратора, последняя попытка ученика), а в
 * них — письменные задания с непустым ответом, ещё не оценённые куратором
 * и без черновика ИИ. Задание, которое уже ушло на проверку и ещё не
 * вернулось, пропускается; но выгрузка в Desktop могла потеряться, поэтому
 * через EXPORT_TTL_DAYS такое задание снова можно выгрузить.
 */
class AiGradingQueue
{
    public const EXPORT_TTL_DAYS = 3;

    /**
     * Сгруппировано по работе: ученику проверяем все задания работы разом.
     *
     * @return Collection<int, array{submission: Submission, tasks: Collection<int, HomeworkTask>}>
     */
    public function bySubmission(?Collection $queue = null): Collection
    {
        $submissions = EloquentCollection::make(($queue ?? Submission::pendingReviewQueue())->all())
            ->loadMissing(['homework.tasks.task.category', 'homework.course.category']);

        $out = collect();
        foreach ($submissions as $s) {
            $tasks = ($s->homework?->tasks ?? collect())
                ->filter(fn (HomeworkTask $t) => $this->needsGrading($s, $t))
                ->values();
            if ($tasks->isNotEmpty()) {
                $out->push(['submission' => $s, 'tasks' => $tasks]);
            }
        }

        return $out;
    }

    /** То же плоским списком пар [Submission, HomeworkTask]. */
    public function tasks(?Collection $queue = null): Collection
    {
        return $this->bySubmission($queue)
            ->flatMap(fn ($g) => $g['tasks']->map(fn ($t) => [$g['submission'], $t]))
            ->values();
    }

    private function needsGrading(Submission $s, HomeworkTask $t): bool
    {
        if ($t->isAutoGradable()) {
            return false;
        }

        $key = (string) $t->id;
        $row = ($s->per_task_results ?? [])[$key] ?? [];
        if (isset($row['score']) || !empty($row['skipped'])) {
            return false;
        }

        if (trim((string) (($s->answers ?? [])[$key] ?? '')) === '') {
            return false;
        }

        $draft = ($s->ai_drafts ?? [])[$key] ?? [];
        if (isset($draft['score'])) {
            return false;
        }

        return match ($draft['status'] ?? null) {
            'pending'  => false,
            'exported' => Carbon::parse($draft['exported_at'] ?? '2000-01-01')
                ->lt(now()->subDays(self::EXPORT_TTL_DAYS)),
            default    => true,
        };
    }
}
