<?php

namespace App\Service\Bank;

use App\Models\Category;
use App\Models\Task;
use App\Models\TaskCriteria;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Заливка банка заданий из docs/bank/tasks.json (собирает
 * tools/bank/build_from_egehub.py) в общую таблицу tasks — тот же банк,
 * что у домашек. Ключ перезаливки — external_id (id задания ege-hub).
 *
 * Правки автора важнее файла: если содержание задания в базе уже не
 * совпадает с тем, что залил прошлый импорт (import_hash), автор правил
 * его в админке — содержание не трогаем, обновляем только служебные поля
 * (блок, тема, группа). Задание, пропавшее из файла, не удаляется (на него
 * ссылаются попытки и домашки), а скрывается (status = hidden).
 *
 * Критерии номера (TaskCriteria) создаются, только если их ещё нет:
 * баллы — из файла, текст для 17–25 — из правил ФИПИ
 * resources/ai/grading/obshchestvoznanie.md.
 */
class BankImporter
{
    public const SOURCE = 'egehub';

    /** Поля содержания: их отпечаток — import_hash. */
    private const CONTENT = [
        'number', 'type', 'question_text', 'passage_text', 'options', 'matches',
        'left_title', 'right_title', 'answer', 'explanation', 'image_path', 'order_matters',
    ];

    private const TYPES = ['test', 'matching', 'written', 'image_written'];

    /** @return string[] ошибки; пусто — файл можно заливать */
    public static function validate(mixed $data): array
    {
        if (! is_array($data) || ! isset($data['tasks']) || ! is_array($data['tasks'])) {
            return ['В файле нет списка tasks'];
        }
        $errors = [];
        $seen = [];
        $blocks = array_keys(config('statements.blocks'));
        foreach ($data['tasks'] as $i => $row) {
            $where = 'tasks['.$i.']'.(isset($row['ext']) ? ' (ext '.$row['ext'].')' : '');
            if (! is_array($row)) {
                $errors[] = "{$where}: не объект";

                continue;
            }
            if (! is_int($row['ext'] ?? null) || $row['ext'] <= 0) {
                $errors[] = "{$where}: нет ext";
            } elseif (isset($seen[$row['ext']])) {
                $errors[] = "{$where}: ext повторяется";
            } else {
                $seen[$row['ext']] = true;
            }
            if (! is_int($row['number'] ?? null) || $row['number'] < 1 || $row['number'] > 25) {
                $errors[] = "{$where}: number должен быть от 1 до 25";
            }
            if (! in_array($row['type'] ?? null, self::TYPES, true)) {
                $errors[] = "{$where}: type должен быть одним из: ".implode(', ', self::TYPES);
            }
            if (! in_array($row['status'] ?? null, ['active', 'hidden'], true)) {
                $errors[] = "{$where}: status — active или hidden";
            }
            if (($row['status'] ?? null) === 'active') {
                if (! in_array($row['block'] ?? null, $blocks, true)) {
                    $errors[] = "{$where}: неизвестный block";
                }
                if (trim((string) ($row['question'] ?? '')) === '' || trim((string) ($row['answer'] ?? '')) === '') {
                    $errors[] = "{$where}: у active должны быть question и answer";
                }
            }
        }

        return $errors;
    }

    /**
     * @return array{added:int, changed:int, kept:int, edited:int, hidden:int, active:int, criteria:int}
     */
    public function plan(array $data, Category $category): array
    {
        return $this->run($data, $category, dryRun: true);
    }

    public function apply(array $data, Category $category, ?string $imagesDir = null): array
    {
        return $this->run($data, $category, dryRun: false, imagesDir: $imagesDir);
    }

    /** Категория банка: по названию (config terms.subject_keyword) или явно по id. */
    public static function findCategory(?int $id = null): ?Category
    {
        if ($id) {
            return Category::find($id);
        }
        $keyword = mb_strtolower((string) config('terms.subject_keyword'));

        return Category::all()->first(fn (Category $c) => $keyword !== '' && str_contains(mb_strtolower($c->title), $keyword));
    }

    private function run(array $data, Category $category, bool $dryRun, ?string $imagesDir = null): array
    {
        $stats = ['added' => 0, 'changed' => 0, 'kept' => 0, 'edited' => 0, 'hidden' => 0, 'active' => 0, 'criteria' => 0];
        $rows = collect($data['tasks'])->keyBy('ext');
        $existing = Task::where('source', self::SOURCE)->get()->keyBy('external_id');

        $work = function () use ($data, $rows, $existing, $category, $dryRun, &$stats) {
            foreach ($rows as $ext => $row) {
                $content = $this->contentFor($row);
                $meta = [
                    'source' => self::SOURCE,
                    'external_id' => $ext,
                    'category_id' => $category->id,
                    'block' => $row['block'] ?? null,
                    'theme' => isset($row['theme']) ? mb_substr($row['theme'], 0, 500) : null,
                    'group_key' => $row['group'] ?? null,
                    'status' => $row['status'],
                    'issue' => isset($row['issue']) ? mb_substr((string) $row['issue'], 0, 500) : null,
                    'also' => $row['also'] ?: null,
                    'is_public' => false,
                ];
                if ($row['status'] === 'active') {
                    $stats['active']++;
                }

                /** @var Task|null $task */
                $task = $existing->get($ext);
                if (! $task) {
                    $stats['added']++;
                    if (! $dryRun) {
                        $task = new Task;
                        $task->forceFill($meta + $content);
                        $task->import_hash = self::hash($task);
                        $task->save();
                    }

                    continue;
                }

                $edited = $task->import_hash !== null && $task->import_hash !== self::hash($task);
                if ($edited) {
                    // Правил автор — содержание и статус не трогаем.
                    $stats['edited']++;
                    unset($meta['status'], $meta['issue']);
                    $task->forceFill($meta);
                } else {
                    $task->forceFill($meta + $content);
                    $task->import_hash = self::hash($task);
                }
                if ($task->isDirty()) {
                    $edited ?: $stats['changed']++;
                    $dryRun ?: $task->save();
                } elseif (! $edited) {
                    $stats['kept']++;
                }
            }

            // Пропали из файла — скрываем.
            foreach ($existing as $ext => $task) {
                if (! $rows->has($ext) && $task->status !== 'hidden') {
                    $stats['hidden']++;
                    if (! $dryRun) {
                        $task->forceFill(['status' => 'hidden', 'issue' => 'нет в файле импорта'])->save();
                    }
                }
            }

            $stats['criteria'] = $this->ensureCriteria($category, $data['max_by_number'] ?? [], $dryRun);
        };

        $dryRun ? $work() : DB::transaction($work);

        if (! $dryRun && $imagesDir) {
            $this->copyImages($rows, $imagesDir);
        }

        return $stats;
    }

    private function contentFor(array $row): array
    {
        $isMatching = $row['type'] === 'matching';

        return [
            'number' => (string) $row['number'],
            'type' => $row['type'],
            'question_text' => $row['question'],
            'passage_text' => $row['passage'] ?? null,
            'options' => $row['options'] ?? null,
            'matches' => $row['matches'] ?? null,
            'left_title' => $row['left_title'] ?? null,
            'right_title' => $row['right_title'] ?? null,
            'answer' => $row['answer'],
            'explanation' => $row['explanation'] ?? null,
            'image_path' => ! empty($row['image']) ? 'bank/'.$row['image'] : null,
            // В соответствиях порядок важен (А, Б, В…); в выборе — нет:
            // «Последовательность» ege-hub в 12/14 — тоже выбор без порядка.
            'order_matters' => $isMatching,
        ];
    }

    /** Отпечаток содержания задания — после приведения типов моделью. */
    public static function hash(Task $task): string
    {
        $values = [];
        foreach (self::CONTENT as $field) {
            $value = $task->getAttribute($field);
            $values[$field] = is_bool($value) ? (int) $value : $value;
        }
        // MySQL хранит JSON с ключами в своём порядке — сортируем, чтобы
        // отпечаток не «поплыл» после перечитывания из базы.
        $values = self::sortKeys($values);

        return sha1(json_encode($values, JSON_UNESCAPED_UNICODE));
    }

    private static function sortKeys(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn ($v) => is_array($v) ? self::sortKeys($v) : $v, $value);
    }

    /** Создаёт критерии номеров, которых ещё нет. @return int сколько создано */
    private function ensureCriteria(Category $category, array $maxByNumber, bool $dryRun): int
    {
        $have = TaskCriteria::where('category_id', $category->id)->pluck('number')->map(fn ($n) => (string) $n)->flip();
        $rules = $this->fipiRules();
        $created = 0;
        foreach ($maxByNumber as $number => $max) {
            if ($have->has((string) $number)) {
                continue;
            }
            $created++;
            if ($dryRun) {
                continue;
            }
            TaskCriteria::create([
                'category_id' => $category->id,
                'number' => (string) $number,
                'max_score' => (int) $max,
                'criteria' => $rules[(int) $number] ?? 'Проверяется автоматически по ключу ответа.',
            ]);
        }

        return $created;
    }

    /** Разделы «## Задание N — …» из правил ФИПИ → [N => текст]. */
    private function fipiRules(): array
    {
        $path = resource_path('ai/grading/obshchestvoznanie.md');
        if (! is_file($path)) {
            return [];
        }
        $rules = [];
        $parts = preg_split('/^## /m', (string) file_get_contents($path));
        foreach ($parts as $part) {
            if (preg_match('/^Задание (\d+)\b/u', $part, $m)) {
                $rules[(int) $m[1]] = trim('## '.$part);
            }
        }

        return $rules;
    }

    private function copyImages($rows, string $dir): void
    {
        $disk = Storage::disk('public');
        foreach ($rows as $row) {
            if (empty($row['image'])) {
                continue;
            }
            $src = rtrim($dir, '/\\').DIRECTORY_SEPARATOR.$row['image'];
            $dst = 'bank/'.$row['image'];
            if (is_file($src) && ! $disk->exists($dst)) {
                $disk->put($dst, file_get_contents($src));
            }
        }
    }
}
