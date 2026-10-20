<?php

namespace App\Service\Homework;

use App\Models\AiGradingBatch;
use App\Models\HomeworkTask;
use App\Models\Submission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * ИИ-проверка без API: админ выгружает порцию непроверенных письменных
 * заданий в JSON, проверяет её в Claude Desktop скиллом ege-homework-check
 * (resources/ai/skill) и загружает результат обратно. Результат становится
 * черновиком куратора в submissions.ai_drafts — так же, как при проверке
 * через API, куратор его подтверждает или правит.
 *
 * Форматы файлов описаны в resources/ai/skill/ege-homework-check/references/format.md
 * — при изменении полей здесь поменяйте и там (и FORMAT/RESULT_FORMAT).
 */
class DesktopGradingExchange
{
    public const FORMAT = 'poltavskiy-homework-check/v1';
    public const RESULT_FORMAT = 'poltavskiy-homework-check-result/v1';

    /**
     * Примерный размер порции. Работа ученика целиком попадает в одну
     * порцию, поэтому порция может быть больше (одна большая работа —
     * всё равно целиком).
     */
    public const PORTION = 30;

    public function __construct(
        private ClaudeGrader $grader,
        private AiGradingQueue $queue,
        private DesktopGradingSkill $skill,
    )
    {
    }

    /**
     * Для блока на /mentor/submissions: сколько заданий / работ ждут
     * выгрузки (всего и по предметам), открытые выгрузки без результата.
     */
    public function stats(?Collection $queue = null): array
    {
        $groups = $this->queue->bySubmission($queue);
        $tasks = $groups->sum(fn ($g) => $g['tasks']->count());

        $bySubject = [];
        foreach ($groups as $g) {
            foreach ($g['tasks'] as $t) {
                $subject = $this->subjectOf($g['submission'], $t);
                $bySubject[$subject] = ($bySubject[$subject] ?? 0) + 1;
            }
        }
        arsort($bySubject);

        return [
            'tasks'       => $tasks,
            'submissions' => $groups->count(),
            'portions'    => (int) ceil($tasks / self::PORTION),
            'by_subject'  => $bySubject,
            'open'        => AiGradingBatch::where('channel', AiGradingBatch::CHANNEL_DESKTOP)
                ->where('status', 'in_progress')->orderBy('id')->get(),
        ];
    }

    /**
     * Выгрузить следующую порцию (по всем предметам или по одному).
     * null — выгружать нечего.
     *
     * @return array{record: AiGradingBatch, filename: string, json: string}|null
     */
    public function export(?int $userId, ?string $subject = null): ?array
    {
        $pairs = [];
        $count = 0;
        foreach ($this->queue->bySubmission() as $group) {
            $tasks = $subject === null
                ? $group['tasks']
                : $group['tasks']->filter(fn ($t) => $this->subjectOf($group['submission'], $t) === $subject);
            if ($tasks->isEmpty()) {
                continue;
            }
            // Работа ученика целиком — порция может чуть превысить PORTION.
            if ($count > 0 && $count + $tasks->count() > self::PORTION) {
                break;
            }
            foreach ($tasks as $t) {
                $pairs[] = [$group['submission'], $t];
            }
            $count += $tasks->count();
        }
        if (!$pairs) {
            return null;
        }

        $record = AiGradingBatch::create([
            'channel'       => AiGradingBatch::CHANNEL_DESKTOP,
            'external_id'   => 'exp-' . now()->format('Ymd-Hi') . '-' . Str::lower(Str::random(4)),
            'status'        => 'in_progress',
            'request_count' => count($pairs),
            'items'         => array_map(fn ($p) => "s{$p[0]->id}-t{$p[1]->id}", $pairs),
            'created_by'    => $userId,
        ]);

        $marks = [];
        foreach ($pairs as [$s, $t]) {
            $marks[$s->id][(string) $t->id] = (int) ($t->max_score ?? 3);
        }
        foreach ($marks as $submissionId => $tasks) {
            $this->updateDrafts($submissionId, function (array $drafts) use ($tasks, $record) {
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
        foreach ($this->itemsBySubmission($record) as $submissionId => $keys) {
            $this->updateDrafts($submissionId, function (array $drafts) use ($keys, $record, &$returned) {
                foreach ($keys as $key) {
                    if ($this->isMarked($drafts[$key] ?? [], $record)) {
                        unset($drafts[$key]);
                        $returned++;
                    }
                }
                return $drafts;
            });
        }
        $record->update(['status' => 'canceled', 'ended_at' => now()]);

        return $returned;
    }

    private function subjectOf(Submission $s, HomeworkTask $t): string
    {
        return $this->grader->subjectFor($s, $t) ?: 'Предмет не указан';
    }

    /** Файл выгрузки для заданий $pairs из выгрузки $record. */
    private function file(AiGradingBatch $record, array $pairs): array
    {
        // Текст-источник к 17–20 один на 4 задания — кладём один раз.
        $sources = [];
        $items = [];
        foreach ($pairs as [$s, $t]) {
            $p = $this->grader->payloadFor($s, $t);

            $sourceId = null;
            $passage = trim((string) $p['passage']);
            if ($passage !== '') {
                $sourceId = array_search($passage, $sources, true) ?: 'src' . (count($sources) + 1);
                $sources[$sourceId] = $passage;
            }

            $rules = $this->grader->subjectRulesFile($p['subject']);
            $items[] = [
                'id'                     => "s{$s->id}-t{$t->id}",
                'submission_id'          => $s->id,
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

    /** Пары [Submission, HomeworkTask] выгрузки, которые ещё ждут её результата. */
    private function pendingPairs(AiGradingBatch $record): \Generator
    {
        $bySubmission = $this->itemsBySubmission($record);
        $submissions = Submission::with(['homework.tasks.task.category', 'homework.course.category'])
            ->whereKey(array_keys($bySubmission))->get();

        foreach ($submissions as $s) {
            $drafts = $s->ai_drafts ?? [];
            foreach ($bySubmission[$s->id] as $key) {
                $task = $s->homework?->tasks->firstWhere('id', (int) $key);
                if ($task && $this->isMarked($drafts[$key] ?? [], $record)) {
                    yield [$s, $task];
                }
            }
        }
    }

    /** @return array<int, list<string>> submission_id → ключи заданий выгрузки */
    private function itemsBySubmission(AiGradingBatch $record): array
    {
        $out = [];
        foreach ($record->items ?? [] as $id) {
            if (preg_match('/^s(\d+)-t(\d+)$/', $id, $m)) {
                $out[(int) $m[1]][] = $m[2];
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
     * review — работы с флагом «Проверьте сами»: submission_id → сколько таких заданий.
     *
     * @return array{export_id: string, imported: int, review: array<int,int>, skipped: list<string>, missing: list<string>}
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
        $bySubmission = [];
        $skipped = [];
        foreach ((array) ($data['results'] ?? []) as $result) {
            $id = (string) ($result['id'] ?? '');
            if (!isset($expected[$id]) || !preg_match('/^s(\d+)-t(\d+)$/', $id, $m)) {
                $skipped[] = "{$id}: задания нет в этой выгрузке";
                continue;
            }
            if (!isset($result['score']) || !is_numeric($result['score'])) {
                $skipped[] = "{$id}: нет балла";
                continue;
            }
            $bySubmission[(int) $m[1]][$m[2]] = $result;
            unset($expected[$id]);
        }

        $imported = 0;
        $review = [];
        foreach ($bySubmission as $submissionId => $results) {
            $this->updateDrafts($submissionId, function (array $drafts, Submission $s) use ($results, $record, &$imported, &$skipped, &$review) {
                foreach ($results as $key => $result) {
                    $id = "s{$s->id}-t{$key}";
                    $current = $drafts[$key] ?? [];
                    if (($current['status'] ?? null) !== 'exported' || ($current['batch'] ?? null) !== $record->id) {
                        $skipped[] = "{$id}: уже загружено или перепроверено";
                        continue;
                    }
                    if (isset((($s->per_task_results ?? [])[$key] ?? [])['score'])) {
                        $skipped[] = "{$id}: куратор уже поставил оценку";
                        unset($drafts[$key]);
                        continue;
                    }
                    $drafts[$key] = $this->grader->draftFromData($result, (int) ($current['max_score'] ?? 0), 'claude-desktop');
                    $imported++;
                    if ($drafts[$key]['needs_review']) {
                        $review[$s->id] = ($review[$s->id] ?? 0) + 1;
                    }
                }
                return $drafts;
            });
        }

        // Не вернувшиеся задания снимаем с отметки «выгружено» — они попадут
        // в следующую выгрузку, а не будут ждать EXPORT_TTL_DAYS.
        $missing = array_keys($expected);
        $missingBySubmission = [];
        foreach ($missing as $id) {
            if (preg_match('/^s(\d+)-t(\d+)$/', $id, $m)) {
                $missingBySubmission[(int) $m[1]][] = $m[2];
            }
        }
        foreach ($missingBySubmission as $submissionId => $keys) {
            $this->updateDrafts($submissionId, function (array $drafts) use ($keys, $record) {
                foreach ($keys as $key) {
                    if (($drafts[$key]['status'] ?? null) === 'exported' && ($drafts[$key]['batch'] ?? null) === $record->id) {
                        unset($drafts[$key]);
                    }
                }
                return $drafts;
            });
        }

        $record->update([
            'status'          => 'ended',
            'ended_at'        => now(),
            'succeeded_count' => $record->succeeded_count + $imported,
            'failed_count'    => count($missing),
        ]);

        return [
            'export_id' => $record->external_id,
            'imported'  => $imported,
            'review'    => $review,
            'skipped'   => $skipped,
            'missing'   => $missing,
        ];
    }

    /** Перечитать работу под блокировкой: параллельно куратор может сохранять оценки. */
    private function updateDrafts(int $submissionId, callable $mutate): void
    {
        DB::transaction(function () use ($submissionId, $mutate) {
            $s = Submission::whereKey($submissionId)->lockForUpdate()->first();
            if (!$s) {
                return;
            }
            $s->ai_drafts = $mutate($s->ai_drafts ?? [], $s);
            $s->save();
        });
    }
}
