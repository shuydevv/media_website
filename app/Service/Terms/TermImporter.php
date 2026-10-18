<?php

namespace App\Service\Terms;

use App\Models\Term;
use App\Models\TermFeature;
use Illuminate\Support\Facades\DB;

/**
 * Заливка базы терминов из JSON (docs/terms/egkh_terms_18.json). Единственный
 * способ менять термины и признаки: правки вносятся в файл и перезаливаются.
 *
 * Перезаливка не трогает прогресс учеников: термины сопоставляются по slug,
 * признаки — по id из файла (term_features.key). То, чего в новом файле нет,
 * не удаляется, а скрывается (is_active = false) — на термин ссылается
 * user_terms, на признак — term_reports.
 */
class TermImporter
{
    private const TERM_FIELDS = ['title', 'qualifier', 'other_names', 'image', 'code', 'priority', 'parent', 'confusable_with'];

    /**
     * Проверка целостности файла. Пустой массив — файл можно заливать.
     *
     * @return string[] ошибки по-русски
     */
    public static function validate(mixed $rows): array
    {
        if (!is_array($rows) || $rows === [] || !array_is_list($rows)) {
            return ['Файл должен быть непустым JSON-массивом терминов.'];
        }

        $errors = [];
        $slugs = [];
        $featureKeys = [];
        $groupSizes = [];

        foreach ($rows as $i => $row) {
            $label = 'Термин №'.($i + 1);
            if (!is_array($row) || !is_string($row['slug'] ?? null) || $row['slug'] === '') {
                $errors[] = "{$label}: нет поля slug.";
                continue;
            }

            $slug = $row['slug'];
            $label = "«{$slug}»";
            if (isset($slugs[$slug])) {
                $errors[] = "{$label}: slug встречается дважды.";
            }
            $slugs[$slug] = true;

            if (!is_string($row['title'] ?? null) || trim($row['title']) === '') {
                $errors[] = "{$label}: пустое название.";
            }
            if (!preg_match('/^\d+\.\d+$/', (string) ($row['code'] ?? ''))) {
                $errors[] = "{$label}: код темы должен быть вида «2.10».";
            }
            if (!array_key_exists($row['priority'] ?? null, Term::LEVELS)) {
                $errors[] = "{$label}: priority — уровень от 1 до ".count(Term::LEVELS).'.';
            }
            if (!is_array($row['other_names'] ?? [])) {
                $errors[] = "{$label}: other_names должен быть списком.";
            }

            $features = $row['features'] ?? null;
            if (!is_array($features) || $features === []) {
                $errors[] = "{$label}: нет признаков.";
                continue;
            }

            $ownGroups = [];
            foreach ($features as $feature) {
                $key = is_array($feature) ? ($feature['id'] ?? null) : null;
                if (!is_string($key) || $key === '' || trim((string) ($feature['text'] ?? '')) === '') {
                    $errors[] = "{$label}: признак без id или текста.";
                    continue;
                }
                if (isset($featureKeys[$key])) {
                    $errors[] = "Признак «{$key}» встречается дважды.";
                }
                $featureKeys[$key] = true;

                $group = $feature['group'] ?? null;
                if ($group !== null) {
                    $groupSizes[$group] = ($groupSizes[$group] ?? 0) + 1;
                    // Группа разводит признаки РАЗНЫХ терминов; два признака
                    // одного термина в ней — почти наверняка опечатка в метке.
                    if (isset($ownGroups[$group])) {
                        $errors[] = "{$label}: два признака в одной группе {$group}.";
                    }
                    $ownGroups[$group] = true;
                }
            }
        }

        foreach ($groupSizes as $group => $size) {
            if ($size < 2) {
                $errors[] = "Группа {$group} состоит из одного признака.";
            }
        }

        $parentOf = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['slug'])) {
                continue;
            }
            $slug = $row['slug'];

            $parent = $row['parent'] ?? null;
            if ($parent !== null) {
                if (!isset($slugs[$parent])) {
                    $errors[] = "«{$slug}»: родителя «{$parent}» нет в файле.";
                } elseif ($parent === $slug) {
                    $errors[] = "«{$slug}»: термин указан родителем самому себе.";
                } else {
                    $parentOf[$slug] = $parent;
                }
            }

            foreach ((array) ($row['confusable_with'] ?? []) as $other) {
                if (!isset($slugs[$other])) {
                    $errors[] = "«{$slug}»: в confusable_with указан «{$other}», которого нет в файле.";
                }
            }

            $notes = $row['pair_notes'] ?? [];
            if (!is_array($notes) || ($notes !== [] && array_is_list($notes))) {
                $errors[] = "«{$slug}»: pair_notes должен быть объектом вида {\"slug другого термина\": \"заметка\"}.";
            } else {
                foreach ($notes as $other => $note) {
                    if (!isset($slugs[$other]) || $other === $slug) {
                        $errors[] = "«{$slug}»: заметка в pair_notes ссылается на «{$other}» — такого другого термина в файле нет.";
                    } elseif (!is_string($note) || trim($note) === '') {
                        $errors[] = "«{$slug}»: пустая заметка в pair_notes для «{$other}».";
                    }
                }
            }
        }

        foreach (array_keys($parentOf) as $slug) {
            $seen = [$slug => true];
            $current = $parentOf[$slug];
            while ($current !== null) {
                if (isset($seen[$current])) {
                    $errors[] = "«{$slug}»: цепочка parent замкнута в кольцо.";
                    break;
                }
                $seen[$current] = true;
                $current = $parentOf[$current] ?? null;
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * Что изменится при заливке — без записи в базу. Формат тот же, что
     * возвращает apply().
     *
     * @return array{terms: array{added: string[], changed: string[], removed: string[]}, features: array{added: int, changed: int, removed: int}}
     */
    public function plan(array $rows): array
    {
        $existing = Term::query()->with(['parent:id,slug', 'confusables:id,slug'])->get()->keyBy('slug');
        $existingFeatures = TermFeature::query()->get()->keyBy('key');

        $plan = [
            'terms' => ['added' => [], 'changed' => [], 'removed' => []],
            'features' => ['added' => 0, 'changed' => 0, 'removed' => 0],
        ];

        $seenSlugs = [];
        $seenKeys = [];

        foreach ($rows as $row) {
            $slug = $row['slug'];
            $seenSlugs[$slug] = true;
            $term = $existing->get($slug);

            $termChanged = false;
            if (!$term) {
                $plan['terms']['added'][] = $slug;
            } elseif (!$term->is_active || $this->snapshot($term) != $this->normalizeRow($row)) {
                $termChanged = true;
            }

            foreach ($row['features'] as $position => $feature) {
                $seenKeys[$feature['id']] = true;
                $current = $existingFeatures->get($feature['id']);

                if (!$current) {
                    $plan['features']['added']++;
                    $termChanged = $termChanged || (bool) $term;
                } elseif (
                    !$current->is_active
                    || $current->text !== $feature['text']
                    || $current->group !== ($feature['group'] ?? null)
                    || (int) $current->position !== $position + 1
                    || ($term && (int) $current->term_id !== (int) $term->id)
                ) {
                    $plan['features']['changed']++;
                    $termChanged = $termChanged || (bool) $term;
                }
            }

            if ($term) {
                foreach ($existingFeatures as $key => $current) {
                    if ((int) $current->term_id === (int) $term->id && $current->is_active && !isset($seenKeys[$key])) {
                        $termChanged = true;
                    }
                }
            }

            if ($termChanged) {
                $plan['terms']['changed'][] = $slug;
            }
        }

        foreach ($existing as $slug => $term) {
            if ($term->is_active && !isset($seenSlugs[$slug])) {
                $plan['terms']['removed'][] = $slug;
            }
        }
        foreach ($existingFeatures as $key => $current) {
            if ($current->is_active && !isset($seenKeys[$key])) {
                $plan['features']['removed']++;
            }
        }

        return $plan;
    }

    /**
     * Заливает файл. Вызывающий код обязан сначала прогнать validate():
     * здесь целостность не перепроверяется.
     */
    public function apply(array $rows): array
    {
        return DB::transaction(function () use ($rows) {
            $plan = $this->plan($rows);

            $idBySlug = [];
            $seenKeys = [];

            foreach ($rows as $row) {
                $normalized = $this->normalizeRow($row);

                $term = Term::updateOrCreate(['slug' => $row['slug']], [
                    'source_id' => isset($row['id']) ? (int) $row['id'] : null,
                    'title' => $normalized['title'],
                    'qualifier' => $normalized['qualifier'],
                    'other_names' => $normalized['other_names'],
                    'pair_notes' => $normalized['pair_notes'] ?: null,
                    'image' => $normalized['image'],
                    'code' => $normalized['code'],
                    'section' => $this->section($normalized['code']),
                    'priority' => $normalized['priority'],
                    'sort_order' => $this->sortOrder($normalized['priority'], $normalized['code'], (int) ($row['id'] ?? 0)),
                    'is_active' => true,
                ]);
                $idBySlug[$row['slug']] = $term->id;

                foreach ($row['features'] as $position => $feature) {
                    $seenKeys[] = $feature['id'];
                    TermFeature::updateOrCreate(['key' => $feature['id']], [
                        'term_id' => $term->id,
                        'position' => $position + 1,
                        'text' => $feature['text'],
                        'group' => $feature['group'] ?? null,
                        'is_active' => true,
                    ]);
                }
            }

            // Второй проход: parent и confusable_with ссылаются на термины,
            // которые в первом проходе могли быть ещё не созданы.
            foreach ($rows as $row) {
                $term = Term::find($idBySlug[$row['slug']]);
                $parent = $row['parent'] ?? null;
                $term->parent_id = $parent !== null ? $idBySlug[$parent] : null;
                $term->save();

                $term->confusables()->sync(
                    array_map(fn (string $slug) => $idBySlug[$slug], $row['confusable_with'] ?? [])
                );
            }

            Term::query()->whereNotIn('id', array_values($idBySlug))->update(['is_active' => false]);
            TermFeature::query()->whereNotIn('key', $seenKeys)->update(['is_active' => false]);

            return $plan;
        });
    }

    /**
     * Порядок изучения: сначала приоритет, внутри — раздел и тема
     * кодификатора, при равенстве — порядок в файле.
     */
    public function sortOrder(int $priority, string $code, int $sourceId): int
    {
        [$section, $topic] = array_map('intval', explode('.', $code));

        return $priority * 10_000_000 + $section * 100_000 + $topic * 1_000 + min($sourceId, 999);
    }

    private function section(string $code): int
    {
        return (int) explode('.', $code)[0];
    }

    /**
     * Поля термина из файла в виде, сравнимом со snapshot() из базы.
     */
    private function normalizeRow(array $row): array
    {
        $confusable = array_values($row['confusable_with'] ?? []);
        sort($confusable);

        return [
            'title' => trim($row['title']),
            'qualifier' => ($row['qualifier'] ?? null) !== null && trim($row['qualifier']) !== '' ? trim($row['qualifier']) : null,
            'other_names' => array_values($row['other_names'] ?? []),
            'pair_notes' => $this->sortedNotes($row['pair_notes'] ?? []),
            'image' => $row['image'] ?? null,
            'code' => (string) $row['code'],
            'priority' => (int) $row['priority'],
            'parent' => $row['parent'] ?? null,
            'confusable_with' => $confusable,
        ];
    }

    /** Заметки к парам в одном порядке — иначе сравнение «файл / база» ловило бы перестановку ключей. */
    private function sortedNotes(array $notes): array
    {
        $notes = array_map(fn ($note) => trim((string) $note), $notes);
        ksort($notes);

        return $notes;
    }

    private function snapshot(Term $term): array
    {
        $confusable = $term->confusables->pluck('slug')->all();
        sort($confusable);

        return [
            'title' => $term->title,
            'qualifier' => $term->qualifier,
            'other_names' => array_values($term->other_names ?? []),
            'pair_notes' => $this->sortedNotes($term->pair_notes ?? []),
            'image' => $term->image,
            'code' => $term->code,
            'priority' => (int) $term->priority,
            'parent' => $term->parent?->slug,
            'confusable_with' => $confusable,
        ];
    }
}
