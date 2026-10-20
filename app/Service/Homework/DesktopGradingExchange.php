<?php

namespace App\Service\Homework;

use App\Models\AiGradingBatch;
use App\Models\Submission;
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

    public function __construct(private ClaudeGrader $grader, private AiGradingQueue $queue)
    {
    }

    /** Сколько заданий / работ ждут выгрузки и сколько выгружено, но не вернулось. */
    public function stats(?\Illuminate\Support\Collection $queue = null): array
    {
        $groups = $this->queue->bySubmission($queue);

        return [
            'tasks'       => $groups->sum(fn ($g) => $g['tasks']->count()),
            'submissions' => $groups->count(),
            'open'        => AiGradingBatch::where('channel', AiGradingBatch::CHANNEL_DESKTOP)
                ->where('status', 'in_progress')->orderByDesc('id')->get(),
        ];
    }

    /**
     * Выгрузить следующую порцию. null — выгружать нечего.
     *
     * @return array{record: AiGradingBatch, filename: string, json: string}|null
     */
    public function export(?int $userId): ?array
    {
        $groups = collect();
        $count = 0;
        foreach ($this->queue->bySubmission() as $group) {
            if ($count > 0 && $count + $group['tasks']->count() > self::PORTION) {
                break;
            }
            $groups->push($group);
            $count += $group['tasks']->count();
        }
        if ($groups->isEmpty()) {
            return null;
        }

        $exportId = 'exp-' . now()->format('Ymd-Hi') . '-' . Str::lower(Str::random(4));

        // Текст-источник к 17–20 один на 4 задания — кладём один раз.
        $sources = [];
        $items = [];
        $marks = [];
        foreach ($groups as $group) {
            $s = $group['submission'];
            foreach ($group['tasks'] as $t) {
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
                $marks[$s->id][(string) $t->id] = (int) $p['max_score'];
            }
        }

        $record = AiGradingBatch::create([
            'channel'       => AiGradingBatch::CHANNEL_DESKTOP,
            'external_id'   => $exportId,
            'status'        => 'in_progress',
            'request_count' => count($items),
            'items'         => array_column($items, 'id'),
            'created_by'    => $userId,
        ]);

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

        $json = json_encode([
            'format'     => self::FORMAT,
            'export_id'  => $exportId,
            'created_at' => now()->toIso8601String(),
            'note'       => 'Проверь этот файл скиллом ege-homework-check и верни файл результата.',
            'sources'    => (object) $sources,
            'items'      => $items,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

        return ['record' => $record, 'filename' => "homework-check-{$exportId}.json", 'json' => $json];
    }

    /**
     * Загрузить файл результата. Возвращает отчёт для админа.
     *
     * @return array{export_id: string, imported: int, skipped: list<string>, missing: list<string>}
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
        foreach ($bySubmission as $submissionId => $results) {
            $this->updateDrafts($submissionId, function (array $drafts, Submission $s) use ($results, $record, &$imported, &$skipped) {
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

        return ['export_id' => $record->external_id, 'imported' => $imported, 'skipped' => $skipped, 'missing' => $missing];
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
