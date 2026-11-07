<?php

namespace App\Service\Statements;

use App\Models\Statement;
use Illuminate\Support\Facades\DB;

/**
 * Заливка базы «Верных суждений» из JSON (формат —
 * docs/statements/Верные суждения — механика.md). Ключ перезаливки —
 * id суждения. Суждение, пропавшее из файла, не удаляется (на него
 * ссылаются ответы учеников), а получает status = removed и скрывается.
 * Прогресс учеников не трогает.
 */
class StatementImporter
{
    /** @return string[] ошибки; пусто — файл можно заливать */
    public static function validate(mixed $data): array
    {
        if (! is_array($data) || ! isset($data['statements']) || ! is_array($data['statements'])) {
            return ['В файле нет списка statements'];
        }

        $errors = [];
        $seen = [];
        $blocks = array_keys(config('statements.blocks'));

        foreach ($data['statements'] as $i => $row) {
            $where = 'statements['.$i.']'.(isset($row['id']) && is_string($row['id']) ? ' ('.$row['id'].')' : '');

            if (! is_array($row)) {
                $errors[] = "{$where}: не объект";

                continue;
            }
            if (! isset($row['id']) || ! is_string($row['id']) || $row['id'] === '' || mb_strlen($row['id']) > 60) {
                $errors[] = "{$where}: нет id (строка до 60 символов)";
            } elseif (isset($seen[$row['id']])) {
                $errors[] = "{$where}: id повторяется";
            } else {
                $seen[$row['id']] = true;
            }
            if (! in_array($row['block'] ?? null, $blocks, true)) {
                $errors[] = "{$where}: block должен быть одним из: ".implode(', ', $blocks);
            }
            if (! isset($row['text']) || ! is_string($row['text']) || trim($row['text']) === '') {
                $errors[] = "{$where}: пустой text";
            }
            if (! isset($row['is_true']) || ! is_bool($row['is_true'])) {
                $errors[] = "{$where}: is_true должен быть true или false";
            }
            $status = $row['status'] ?? null;
            if (! in_array($status, Statement::STATUSES, true)) {
                $errors[] = "{$where}: status должен быть одним из: ".implode(', ', Statement::STATUSES);
            }
            if ($status === Statement::STATUS_ACTIVE && trim((string) ($row['explanation'] ?? '')) === '') {
                $errors[] = "{$where}: у active должно быть пояснение (explanation)";
            }
            foreach (['about', 'explanation', 'correction', 'issue'] as $field) {
                if (isset($row[$field]) && ! is_string($row[$field])) {
                    $errors[] = "{$where}: {$field} — строка или null";
                }
            }
        }

        return $errors;
    }

    /**
     * Что изменится, без записи.
     *
     * @return array{added: int, changed: int, removed: int, active: int}
     */
    public function plan(array $data): array
    {
        return $this->run($data, false);
    }

    public function apply(array $data): array
    {
        return $this->run($data, true);
    }

    private function run(array $data, bool $write): array
    {
        $existing = Statement::query()->pluck('hash', 'key');
        $rows = [];
        $added = $changed = 0;
        $now = now();

        foreach ($data['statements'] as $row) {
            $attrs = $this->attributes($row);
            $old = $existing[$attrs['key']] ?? null;
            if ($old === null) {
                $added++;
            } elseif ($old !== $attrs['hash']) {
                $changed++;
            }
            $rows[] = $attrs + ['created_at' => $now, 'updated_at' => $now];
        }

        $keys = array_column($rows, 'key');
        $removed = Statement::query()
            ->whereNotIn('key', $keys)
            ->where('status', '!=', 'removed')
            ->count();

        if ($write) {
            DB::transaction(function () use ($rows, $keys) {
                $update = array_diff(array_keys($rows[0] ?? []), ['key', 'created_at']);
                foreach (array_chunk($rows, 500) as $chunk) {
                    Statement::query()->upsert($chunk, ['key'], $update);
                }
                Statement::query()
                    ->whereNotIn('key', $keys)
                    ->update(['status' => 'removed', 'is_active' => false, 'updated_at' => now()]);
            });
        }

        return [
            'added' => $added,
            'changed' => $changed,
            'removed' => $removed,
            'active' => count(array_filter($rows, fn ($r) => $r['is_active'])),
        ];
    }

    private function attributes(array $row): array
    {
        $attrs = [
            'key' => $row['id'],
            'block' => $row['block'],
            'about' => $this->text($row['about'] ?? null),
            'text' => trim($row['text']),
            'is_true' => $row['is_true'],
            'explanation' => $this->text($row['explanation'] ?? null),
            'correction' => $this->text($row['correction'] ?? null),
            'status' => $row['status'],
            'issue' => $this->text($row['issue'] ?? null),
            'is_active' => $row['status'] === Statement::STATUS_ACTIVE,
            'source_task_id' => $row['source']['task'] ?? null,
            'source_line' => $row['source']['line'] ?? null,
            'theme' => isset($row['source']['theme']) ? mb_substr((string) $row['source']['theme'], 0, 500) : null,
        ];
        $attrs['hash'] = sha1(json_encode($attrs, JSON_UNESCAPED_UNICODE));

        return $attrs;
    }

    private function text(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
