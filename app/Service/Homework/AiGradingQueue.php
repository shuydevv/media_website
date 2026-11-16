<?php

namespace App\Service\Homework;

use App\Models\HomeworkTask;
use App\Models\Submission;
use App\Models\Task;
use App\Models\TaskReview;
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

        return $this->draftAllowsGrading(($s->ai_drafts ?? [])[$key] ?? []);
    }

    /**
     * Ответы второй части из тренажёра «Задания», отправленные куратору
     * (WrittenPractice): не проверенные, не открытые сейчас у куратора,
     * без демо-ученика. В подходе 17+18 — оба задания.
     *
     * @return Collection<int, array{review: TaskReview, tasks: Collection<int, Task>}>
     */
    public function trainerByReview(): Collection
    {
        $reviews = TaskReview::query()
            ->where('status', TaskReview::SENT)
            ->whereHas('user', fn ($q) => $q->where('is_demo', false))
            ->where(fn ($q) => $q->whereNull('locked_by')->orWhere('lock_expires_at', '<=', now()))
            ->orderBy('sent_at')
            ->get();
        if ($reviews->isEmpty()) {
            return collect();
        }

        $tasks = Task::with('category')
            ->whereIn('id', $reviews->pluck('items')->flatten()->map(fn ($id) => (int) $id)->unique()->all())
            ->get()->keyBy('id');

        $out = collect();
        foreach ($reviews as $r) {
            $own = collect($r->items ?? [])
                ->map(fn ($id) => $tasks->get((int) $id))
                ->filter(fn (?Task $t) => $t && $this->trainerNeedsGrading($r, $t))
                ->values();
            if ($own->isNotEmpty()) {
                $out->push(['review' => $r, 'tasks' => $own]);
            }
        }

        return $out;
    }

    private function trainerNeedsGrading(TaskReview $r, Task $t): bool
    {
        $key = (string) $t->id;
        if (trim((string) (($r->answers ?? [])[$key] ?? '')) === '') {
            return false;
        }

        return $this->draftAllowsGrading(($r->ai_drafts ?? [])[$key] ?? []);
    }

    /** Черновика ещё нет, либо выгрузка потерялась (старше EXPORT_TTL_DAYS). */
    private function draftAllowsGrading(array $draft): bool
    {
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
