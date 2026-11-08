<?php

namespace App\Service\Persons;

use App\Models\Person;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Заливка базы тренажёра личностей из docs/personalities/personalities.json.
 * Единственный способ менять личности: правки вносятся в файл и
 * перезаливаются. Устроено как App\Service\Dates\HistImporter.
 *
 * Файл: {levels, eras, roles, order, people: [{id, name, description,
 * association, related: {text, ids} | null, level, era, role, answer,
 * image: {file, source, license, author}}]}. Перезаливка не трогает
 * прогресс: личности сопоставляются по id (slug); чего в файле нет, то
 * скрывается (is_active = false), а не удаляется. Портреты копируются в
 * storage/app/public/persons/ (если файл изменился — перезаписываются).
 */
class PersonImporter
{
    private const FIELDS = [
        'name', 'description', 'association', 'related_text', 'related_slugs', 'era', 'role',
        'level', 'sort_order', 'answer', 'image_path', 'image_meta',
    ];

    /** @return string[] ошибки по-русски; пустой массив — файл можно заливать */
    public static function validate(mixed $data): array
    {
        if (!is_array($data) || !is_array($data['people'] ?? null) || !is_array($data['eras'] ?? null)) {
            return ['Файл должен быть объектом с полями people, eras и order.'];
        }

        $errors = [];
        $ids = [];
        foreach ($data['people'] as $i => $row) {
            $id = is_array($row) ? ($row['id'] ?? null) : null;
            if (!is_string($id) || $id === '') {
                $errors[] = 'Личность №'.($i + 1).': нет поля id.';
                continue;
            }
            $label = "«{$id}»";
            if (isset($ids[$id])) {
                $errors[] = "{$label}: id встречается дважды.";
            }
            $ids[$id] = true;

            foreach (['name', 'description', 'association'] as $field) {
                if (!is_string($row[$field] ?? null) || trim($row[$field]) === '') {
                    $errors[] = "{$label}: пустое поле {$field}.";
                }
            }
            if (!array_key_exists((string) ($row['era'] ?? ''), $data['eras'])) {
                $errors[] = "{$label}: период era не из списка eras.";
            }
            if (!in_array($row['role'] ?? null, Person::ROLES, true)) {
                $errors[] = "{$label}: role — одна из букв ".implode(', ', Person::ROLES).'.';
            }
            if (!array_key_exists($row['level'] ?? null, Person::LEVELS)) {
                $errors[] = "{$label}: level — уровень от 1 до ".count(Person::LEVELS).'.';
            }
            if (isset($row['answer']) && (!is_string($row['answer']) || mb_strlen($row['answer']) > 60)) {
                $errors[] = "{$label}: answer — строка до 60 символов или null.";
            }
        }

        foreach ($data['people'] as $row) {
            foreach ((array) ($row['related']['ids'] ?? []) as $rel) {
                if (!isset($ids[$rel])) {
                    $errors[] = '«'.($row['id'] ?? '?')."»: в related есть неизвестный id «{$rel}».";
                }
            }
        }

        $order = $data['order'] ?? null;
        if (!is_array($order)) {
            $errors[] = 'Нет списка order — порядка изучения.';
        } else {
            $seen = array_count_values(array_filter($order, 'is_string'));
            foreach (array_keys($ids) as $id) {
                if (($seen[$id] ?? 0) !== 1) {
                    $errors[] = "«{$id}»: в order должен стоять ровно один раз.";
                }
            }
            foreach (array_keys($seen) as $id) {
                if (!isset($ids[$id])) {
                    $errors[] = "order: неизвестный id «{$id}».";
                }
            }
        }

        return $errors;
    }

    /** Что изменится, без записи. */
    public function plan(array $data): array
    {
        return $this->run($data, null, true);
    }

    /** @param ?string $imagesRoot папка, относительно которой лежат image.file */
    public function apply(array $data, ?string $imagesRoot = null): array
    {
        return $this->run($data, $imagesRoot, false);
    }

    private function run(array $data, ?string $imagesRoot, bool $dryRun): array
    {
        $rows = $this->rows($data);
        $existing = Person::all()->keyBy('slug');
        $plan = ['added' => [], 'changed' => [], 'removed' => [], 'images' => 0];

        foreach ($rows as $slug => $row) {
            $person = $existing->get($slug);
            if (!$person) {
                $plan['added'][] = $slug;
            } elseif (!$person->is_active || $this->differs($person, $row)) {
                $plan['changed'][] = $slug;
            }
        }
        foreach ($existing as $slug => $person) {
            if ($person->is_active && !isset($rows[$slug])) {
                $plan['removed'][] = $slug;
            }
        }

        if ($dryRun) {
            return $plan;
        }

        if ($imagesRoot !== null) {
            $plan['images'] = $this->copyImages($data, $imagesRoot);
        }

        DB::transaction(function () use ($rows, $existing) {
            foreach ($rows as $slug => $row) {
                Person::updateOrCreate(['slug' => $slug], $row + ['is_active' => true]);
            }
            foreach ($existing as $slug => $person) {
                if (!isset($rows[$slug]) && $person->is_active) {
                    $person->update(['is_active' => false]);
                }
            }
        });

        return $plan;
    }

    /** @return array<string, array> slug => поля persons */
    private function rows(array $data): array
    {
        $position = array_flip(array_values($data['order'] ?? []));
        $rows = [];
        foreach ($data['people'] as $row) {
            $image = $row['image'] ?? null;
            $file = is_array($image) ? ($image['file'] ?? null) : null;
            $answer = isset($row['answer']) && trim((string) $row['answer']) !== '' ? trim($row['answer']) : null;

            $rows[$row['id']] = [
                'name' => trim($row['name']),
                'description' => trim($row['description']),
                'association' => trim($row['association']),
                'related_text' => ($row['related']['text'] ?? null) ?: null,
                'related_slugs' => array_values((array) ($row['related']['ids'] ?? [])) ?: null,
                'era' => (int) $row['era'],
                'role' => $row['role'],
                'level' => (int) $row['level'],
                'sort_order' => (int) ($position[$row['id']] ?? 0) + 1,
                'answer' => $answer,
                'image_path' => $file ? 'persons/'.basename($file) : null,
                'image_meta' => $file ? array_filter([
                    'author' => $image['author'] ?? null,
                    'license' => $image['license'] ?? null,
                    'source' => $image['source'] ?? null,
                ]) : null,
            ];
        }

        return $rows;
    }

    private function differs(Person $person, array $row): bool
    {
        foreach (self::FIELDS as $field) {
            if ($person->{$field} != $row[$field]) {
                return true;
            }
        }

        return false;
    }

    private function copyImages(array $data, string $root): int
    {
        $disk = Storage::disk('public');
        $copied = 0;
        foreach ($data['people'] as $row) {
            $file = $row['image']['file'] ?? null;
            if (!$file) {
                continue;
            }
            $src = rtrim($root, '/\\').DIRECTORY_SEPARATOR.$file;
            $dst = 'persons/'.basename($file);
            if (!is_file($src)) {
                continue;
            }
            $changed = !$disk->exists($dst) || $disk->size($dst) !== filesize($src);
            if ($changed) {
                $disk->put($dst, file_get_contents($src));
                $copied++;
            }
            foreach (Person::SIZES as $kind => $size) {
                $thumb = Person::thumbPathFor($dst, $kind);
                if ($changed || !$disk->exists($thumb)) {
                    $this->makeThumb($src, $thumb, $size);
                }
            }
        }

        return $copied;
    }

    /**
     * Уменьшенная копия портрета (Person::SIZES): WebP, 64 px — ~4 КБ,
     * 256 px — ~15 КБ вместо ~40 КБ портрета. Без GD с WebP копии не будет —
     * страницы возьмут полный портрет.
     */
    private function makeThumb(string $src, string $thumb, int $size): void
    {
        if (!function_exists('imagecreatefromwebp') || !function_exists('imagewebp')) {
            return;
        }
        $image = @imagecreatefromwebp($src);
        if (!$image) {
            return;
        }
        $small = imagecreatetruecolor($size, $size);
        imagealphablending($small, false);
        imagesavealpha($small, true);
        $side = min(imagesx($image), imagesy($image));
        imagecopyresampled($small, $image, 0, 0, (int) ((imagesx($image) - $side) / 2), (int) ((imagesy($image) - $side) / 2), $size, $size, $side, $side);

        ob_start();
        imagewebp($small, null, 80);
        Storage::disk('public')->put($thumb, ob_get_clean());
        imagedestroy($image);
        imagedestroy($small);
    }
}
