<?php

namespace App\Service\Homework;

use App\Models\AiGradingBatch;
use App\Models\HomeworkTask;
use App\Models\Submission;
use App\Models\Task;
use App\Models\TaskReview;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * ИИ-проверка без API: админ выгружает непроверенные письменные задания в
 * JSON, проверяет их в Claude Desktop скиллом ege-homework-check
 * (resources/ai/skill) и загружает результат обратно. Результат становится
 * черновиком куратора — так же, как при проверке через API, куратор его
 * подтверждает или правит.
 *
 * В выгрузку попадает всё, что ждёт проверки, одним файлом (раньше — порции
 * по 30): письменные задания домашек и ответы второй части из тренажёра
 * «Задания» (TaskReview). Ключ задания — «владелец» + задание:
 *   s<submission_id>-t<homework_task_id> — домашка, черновик в submissions.ai_drafts;
 *   r<task_review_id>-t<task_id>         — тренажёр, черновик в task_reviews.ai_drafts.
 * У тренажёрных заданий поле submission_id в файле — строка «r<id>»: навык
 * группирует по нему задания одной работы и возвращает как есть.
 *
 * Форматы файлов описаны в resources/ai/skill/ege-homework-check/references/format.md
 * — при изменении полей здесь поменяйте и там (и FORMAT/RESULT_FORMAT).
 */
class DesktopGradingExchange
{
    public const FORMAT = 'poltavskiy-homework-check/v1';
    public const RESULT_FORMAT = 'poltavskiy-homework-check-result/v1';

    private const ID_PATTERN = '/^([sr])(\d+)-t(\d+)$/';

    public function __construct(
        private ClaudeGrader $grader,
        private AiGradingQueue $queue,
        private DesktopGradingSkill $skill,
    )
    {
    }

    /**
     * Для блока на /mentor/submissions: сколько заданий / работ ждут
     * выгрузки (всего, по предметам и из тренажёра), открытые выгрузки без
     * результата.
     */
    public function stats(?Collection $queue = null): array
    {
        $groups = $this->groups($queue);

        $bySubject = [];
        $trainer = 0;
        foreach ($groups as $g) {
            foreach ($g['tasks'] as $t) {
                $subject = $this->subjectOf($g['owner'], $t);
                $bySubject[$subject] = ($bySubject[$subject] ?? 0) + 1;
                if ($g['owner'] instanceof TaskReview) {
                    $trainer++;
                }
            }
        }
        arsort($bySubject);

        return [
            'tasks'       => $groups->sum(fn ($g) => $g['tasks']->count()),
            'submissions' => $groups->count(),
            'trainer'     => $trainer,
            'by_subject'  => $bySubject,
            'open'        => AiGradingBatch::where('channel', AiGradingBatch::CHANNEL_DESKTOP)
                ->where('status', 'in_progress')->orderBy('id')->get(),
        ];
    }

    /**
     * Выгрузить всё, что ждёт проверки (по всем предметам или по одному).
     * null — выгружать нечего.
     *
     * @return array{record: AiGradingBatch, filename: string, json: string}|null
     */
    public function export(?int $userId, ?string $subject = null): ?array
    {
        $pairs = [];
        foreach ($this->groups() as $group) {
            foreach ($group['tasks'] as $t) {
                if ($subject === null || $this->subjectOf($group['owner'], $t) === $subject) {
                    $pairs[] = [$group['owner'], $t];
                }
            }
        }
        if (!$pairs) {
            return null;
        }

        $record = AiGradingBatch::create([
            'channel'       => AiGradingBatch::CHANNEL_DESKTOP,
            'external_id'   => 'exp-' . now()->format('Ymd-Hi') . '-' . Str::lower(Str::random(4)),
            'status'        => 'in_progress',
            'request_count' => count($pairs),
            'items'         => array_map(fn ($p) => $this->itemId($p[0], $p[1]), $pairs),
            'created_by'    => $userId,
        ]);

        $marks = [];
        foreach ($pairs as [$owner, $t]) {
            $marks[$this->prefix($owner)][$owner->id][(string) $t->id] = (int) ($t->max_score ?? 3);
        }
        foreach ($marks as $prefix => $owners) {
            foreach ($owners as $ownerId => $tasks) {
                $this->updateDrafts($prefix, $ownerId, function (array $drafts) use ($tasks, $record) {
                    foreach ($tasks as $key => $maxScore) {
                        $drafts[$key] = [
                            'status'      => 'exported',
                            'batch'       => $record->id,
                            'exported_at' => now()->toDateTimeString(),
                            'max_score'   => $maxScore,
                        ];
                    }
                    return $drafts;
                });
            }
        }

        return $this->file($record, $pairs);
    }

    /**
     * Скачать открытую выгрузку ещё раз (файл потерялся) — тот же export_id,
     * только задания, которые всё ещё ждут результата этой выгрузки.
     *
     * @return array{record: AiGradingBatch, filename: string, json: string}|null
     */
    public function redownload(AiGradingBatch $record): ?array
    {
        $pairs = iterator_to_array($this->pendingPairs($record), false);

        return $pairs ? $this->file($record, $pairs) : null;
    }

    /** Отменить выгрузку: задания сразу возвращаются в очередь на выгрузку. */
    public function cancel(AiGradingBatch $record): int
    {
        $returned = 0;
        foreach ($this->itemsByOwner($record->items ?? []) as $prefix => $owners) {
            foreach ($owners as $ownerId => $keys) {
                $this->updateDrafts($prefix, $ownerId, function (array $drafts) use ($keys, $record, &$returned) {
                    foreach ($keys as $key) {
                        if ($this->isMarked($drafts[$key] ?? [], $record)) {
                            unset($drafts[$key]);
                            $returned++;
                        }
                    }
                    return $drafts;
                });
            }
        }
        $record->update(['status' => 'canceled', 'ended_at' => now()]);

        return $returned;
    }

    /**
     * Всё, что ждёт выгрузки: сначала домашки, потом ответы из тренажёра.
     *
     * @return Collection<int, array{owner: Submission|TaskReview, tasks: Collection<int, HomeworkTask|Task>}>
     */
    private function groups(?Collection $queue = null): Collection
    {
        return $this->queue->bySubmission($queue)
            ->map(fn ($g) => ['owner' => $g['submission'], 'tasks' => $g['tasks']])
            ->concat($this->queue->trainerByReview()
                ->map(fn ($g) => ['owner' => $g['review'], 'tasks' => $g['tasks']]))
            ->values();
    }

    private function prefix(Model $owner): string
    {
        return $owner instanceof TaskReview ? 'r' : 's';
    }

    private function itemId(Model $owner, Model $task): string
    {
        return $this->prefix($owner) . $owner->id . '-t' . $task->id;
    }

    private function subjectOf(Model $owner, Model $t): string
    {
        $subject = $owner instanceof TaskReview
            ? $t->category?->title
            : $this->grader->subjectFor($owner, $t);

        return $subject ?: 'Предмет не указан';
    }

    /** Файл выгрузки для заданий $pairs из выгрузки $record. */
    private function file(AiGradingBatch $record, array $pairs): array
    {
        // Текст-источник к 17–20 один на 4 задания — кладём один раз.
        $sources = [];
        $items = [];
        foreach ($pairs as [$owner, $t]) {
            $p = $owner instanceof TaskReview
                ? $this->grader->payloadForTrainer($owner, $t)
                : $this->grader->payloadFor($owner, $t);

            $sourceId = null;
            $passage = trim((string) $p['passage']);
            if ($passage !== '') {
                $sourceId = array_search($passage, $sources, true) ?: 'src' . (count($sources) + 1);
                $sources[$sourceId] = $passage;
            }

            $rules = $this->grader->subjectRulesFile($p['subject']);
            $items[] = [
                'id'                     => $this->itemId($owner, $t),
                'submission_id'          => $owner instanceof TaskReview ? 'r' . $owner->id : $owner->id,
                'task_id'                => $t->id,
                'subject'                => $p['subject'],
                'rules'                  => $rules !== null ? Str::beforeLast($rules, '.md') : null,
                'number'                 => $p['number'],
                'max_score'              => (int) $p['max_score'],
                'task_text'              => $p['question'],
                'source_id'              => $sourceId,
                'criteria'               => $p['criteria'],
                'school_recommendations' => $p['recommendations'],
                'exemplar_answer'        => $p['exemplar'] !== '' ? $p['exemplar'] : null,
                'hint'                   => $p['hint'],
                'student_answer'         => $p['student_answer'],
            ];
        }

        $json = json_encode([
            'format'        => self::FORMAT,
            'export_id'     => $record->external_id,
            // Скилл сверяет со своим VERSION: не совпало — навык устарел.
            'rules_version' => $this->skill->version(),
            'created_at'    => $record->created_at->toIso8601String(),
            'note'          => 'Проверь этот файл скиллом ege-homework-check и верни файл результата.',
            'sources'       => (object) $sources,
            'items'         => $items,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

        return ['record' => $record, 'filename' => "homework-check-{$record->external_id}.json", 'json' => $json];
    }

    /** Пары [владелец, задание] выгрузки, которые ещё ждут её результата. */
    private function pendingPairs(AiGradingBatch $record): \Generator
    {
        $byOwner = $this->itemsByOwner($record->items ?? []);

        if (!empty($byOwner['s'])) {
            $submissions = Submission::with(['homework.tasks.task.category', 'homework.course.category'])
                ->whereKey(array_keys($byOwner['s']))->get();
            foreach ($submissions as $s) {
                $drafts = $s->ai_drafts ?? [];
                foreach ($byOwner['s'][$s->id] as $key) {
                    $task = $s->homework?->tasks->firstWhere('id', (int) $key);
                    if ($task && $this->isMarked($drafts[$key] ?? [], $record)) {
                        yield [$s, $task];
                    }
                }
            }
        }

        if (!empty($byOwner['r'])) {
            $reviews = TaskReview::whereKey(array_keys($byOwner['r']))->get();
            $tasks = Task::with('category')
                ->whereIn('id', collect($byOwner['r'])->flatten()->map(fn ($id) => (int) $id)->unique()->all())
                ->get()->keyBy('id');
            foreach ($reviews as $r) {
                $drafts = $r->ai_drafts ?? [];
                foreach ($byOwner['r'][$r->id] as $key) {
                    $task = $tasks->get((int) $key);
                    if ($task && $r->status === TaskReview::SENT && $this->isMarked($drafts[$key] ?? [], $record)) {
                        yield [$r, $task];
                    }
                }
            }
        }
    }

    /**
     * @param  iterable<string>  $ids
     * @return array<string, array<int, list<string>>> префикс (s|r) → id владельца → ключи заданий
     */
    private function itemsByOwner(iterable $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            if (preg_match(self::ID_PATTERN, (string) $id, $m)) {
                $out[$m[1]][(int) $m[2]][] = $m[3];
            }
        }
        return $out;
    }

    private function isMarked(array $draft, AiGradingBatch $record): bool
    {
        return ($draft['status'] ?? null) === 'exported' && ($draft['batch'] ?? null) === $record->id;
    }


    /**
     * Загрузить файл результата. Возвращает отчёт для админа.
     *
     * review — работы с флагом «Проверьте сами»: submission_id → сколько таких заданий;
     * review_trainer — то же для ответов из тренажёра: task_review_id → сколько.
     *
     * @return array{export_id: string, imported: int, review: array<int,int>, review_trainer: array<int,int>, skipped: list<string>, missing: list<string>}
     */
    public function import(string $json): array
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new RuntimeException('Файл не является JSON.');
        }
        if (($data['format'] ?? null) !== self::RESULT_FORMAT) {
            throw new RuntimeException('Это не файл результата проверки (ожидается format = ' . self::RESULT_FORMAT . '). '
                . 'Возможно, загружен файл выгрузки вместо результата.');
        }

        $record = AiGradingBatch::where('channel', AiGradingBatch::CHANNEL_DESKTOP)
            ->where('external_id', (string) ($data['export_id'] ?? ''))->first();
        if (!$record) {
            throw new RuntimeException('Выгрузка «' . ($data['export_id'] ?? '?') . '» не найдена в кабинете.');
        }

        $expected = array_flip($record->items ?? []);
        $byOwner = [];
        $skipped = [];
        foreach ((array) ($data['results'] ?? []) as $result) {
            $id = (string) ($result['id'] ?? '');
            if (!isset($expected[$id]) || !preg_match(self::ID_PATTERN, $id, $m)) {
                $skipped[] = "{$id}: задания нет в этой выгрузке";
                continue;
            }
            if (!isset($result['score']) || !is_numeric($result['score'])) {
                $skipped[] = "{$id}: нет балла";
                continue;
            }
            $byOwner[$m[1]][(int) $m[2]][$m[3]] = $result;
            unset($expected[$id]);
        }

        $imported = 0;
        $review = ['s' => [], 'r' => []];
        foreach ($byOwner as $prefix => $owners) {
            foreach ($owners as $ownerId => $results) {
                $this->updateDrafts($prefix, $ownerId, function (array $drafts, Model $owner) use ($prefix, $results, $record, &$imported, &$skipped, &$review) {
                    foreach ($results as $key => $result) {
                        $id = "{$prefix}{$owner->id}-t{$key}";
                        $current = $drafts[$key] ?? [];
                        if (($current['status'] ?? null) !== 'exported' || ($current['batch'] ?? null) !== $record->id) {
                            $skipped[] = "{$id}: уже загружено или перепроверено";
                            continue;
                        }
                        $graded = $owner instanceof TaskReview
                            ? $owner->status === TaskReview::CHECKED
                            : isset((($owner->per_task_results ?? [])[$key] ?? [])['score']);
                        if ($graded) {
                            $skipped[] = "{$id}: куратор уже поставил оценку";
                            unset($drafts[$key]);
                            continue;
                        }
                        $drafts[$key] = $this->grader->draftFromData($result, (int) ($current['max_score'] ?? 0), 'claude-desktop');
                        $imported++;
                        if ($drafts[$key]['needs_review']) {
                            $review[$prefix][$owner->id] = ($review[$prefix][$owner->id] ?? 0) + 1;
                        }
                    }
                    return $drafts;
                });
            }
        }

        // Не вернувшиеся задания снимаем с отметки «выгружено» — они попадут
        // в следующую выгрузку, а не будут ждать EXPORT_TTL_DAYS.
        $missing = array_keys($expected);
        foreach ($this->itemsByOwner($missing) as $prefix => $owners) {
            foreach ($owners as $ownerId => $keys) {
                $this->updateDrafts($prefix, $ownerId, function (array $drafts) use ($keys, $record) {
                    foreach ($keys as $key) {
                        if ($this->isMarked($drafts[$key] ?? [], $record)) {
                            unset($drafts[$key]);
                        }
                    }
                    return $drafts;
                });
            }
        }

        $record->update([
            'status'          => 'ended',
            'ended_at'        => now(),
            'succeeded_count' => $record->succeeded_count + $imported,
            'failed_count'    => count($missing),
        ]);

        return [
            'export_id'      => $record->external_id,
            'imported'       => $imported,
            'review'         => $review['s'],
            'review_trainer' => $review['r'],
            'skipped'        => $skipped,
            'missing'        => $missing,
        ];
    }

    /**
     * Перечитать работу (или ответ из тренажёра) под блокировкой:
     * параллельно куратор может сохранять оценки.
     */
    private function updateDrafts(string $prefix, int $ownerId, callable $mutate): void
    {
        $class = $prefix === 'r' ? TaskReview::class : Submission::class;
        DB::transaction(function () use ($class, $ownerId, $mutate) {
            $owner = $class::whereKey($ownerId)->lockForUpdate()->first();
            if (!$owner) {
                return;
            }
            $owner->ai_drafts = $mutate($owner->ai_drafts ?? [], $owner);
            $owner->save();
        });
    }
}
