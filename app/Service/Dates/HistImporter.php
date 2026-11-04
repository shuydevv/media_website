<?php

namespace App\Service\Dates;

use App\Models\HistEvent;
use App\Models\HistFact;
use Illuminate\Support\Facades\DB;

/**
 * Заливка базы тренажёра дат из docs/history/events_base.json. Единственный
 * способ менять события, правления и факты: правки вносятся в файл и
 * перезаливаются. Устроено как App\Service\Terms\TermImporter.
 *
 * Файл: {eras: {номер: название}, events: [...], reigns: [...]}. События и
 * правления — один список элементов тренажёра (hist_events.kind), id у них
 * общие. Перезаливка не трогает прогресс учеников: элементы сопоставляются
 * по id из файла (slug), факты — по «slug__fN». Чего в новом файле нет, то
 * не удаляется, а скрывается (is_active = false).
 */
class HistImporter
{
    public const FACT_TYPES = ['person', 'detail', 'cause', 'result'];

    /**
     * Проверка целостности файла. Пустой массив — файл можно заливать.
     *
     * @return string[] ошибки по-русски
     */
    public static function validate(mixed $data): array
    {
        if (!is_array($data) || !is_array($data['eras'] ?? null) || !is_array($data['events'] ?? null)) {
            return ['Файл должен быть объектом с полями eras и events (и reigns).'];
        }

        $errors = [];
        $eras = $data['eras'];
        $items = self::items($data);
        if ($items === []) {
            return ['В файле нет ни событий, ни правлений.'];
        }

        $kindOf = [];
        $groupSizes = [];
        foreach ($items as $i => [$kind, $row]) {
            $id = is_array($row) ? ($row['id'] ?? null) : null;
            if (!is_string($id) || $id === '') {
                $errors[] = 'Элемент №'.($i + 1).': нет поля id.';
                continue;
            }
            $label = "«{$id}»";
            if (isset($kindOf[$id])) {
                $errors[] = "{$label}: id встречается дважды.";
            }
            $kindOf[$id] = $kind;

            if (!is_string($row['title'] ?? null) || trim($row['title']) === '') {
                $errors[] = "{$label}: пустое название.";
            }
            if (!array_key_exists((string) ($row['era'] ?? ''), $eras)) {
                $errors[] = "{$label}: период era не из списка eras.";
            }
            if (!array_key_exists($row['level'] ?? null, HistEvent::LEVELS)) {
                $errors[] = "{$label}: level — уровень от 1 до ".count(HistEvent::LEVELS).'.';
            }
            if (!is_array($row['other_names'] ?? [])) {
                $errors[] = "{$label}: other_names должен быть списком.";
            }

            $date = $row['date'] ?? null;
            $start = $date['start'] ?? null;
            $end = array_key_exists('end', (array) $date) ? $date['end'] : false;
            if (!is_int($start) || $start < 800 || $start > 2100) {
                $errors[] = "{$label}: date.start — год числом.";
                continue;
            }
            if ($end === null && $kind !== HistEvent::KIND_REIGN) {
                $errors[] = "{$label}: у события должен быть date.end (равный началу, если это один год).";
            } elseif ($end !== null && (!is_int($end) || $end < $start)) {
                $errors[] = "{$label}: date.end — год не раньше начала.";
            }
            if (trim((string) ($date['display'] ?? '')) === '') {
                $errors[] = "{$label}: пустой date.display.";
            }

            // Годы элемента не должны стоять в тексте, который ученик видит,
            // отвечая на вопрос о годе: иначе ответ на экране.
            $years = array_unique(array_filter([$start, is_int($end) ? $end : null]));
            $leaks = fn (string $text) => (bool) array_filter($years, fn ($y) => preg_match('/(?<!\d)'.$y.'(?!\d)/', $text));
            if ($leaks((string) ($row['summary'] ?? ''))) {
                $errors[] = "{$label}: в описании (summary) стоит год самого элемента.";
            }
            // Другие названия («Конституция 1993 года») при вводе года не
            // показываются — им год можно.
            if ($leaks((string) ($row['title'] ?? ''))) {
                $errors[] = "{$label}: в названии стоит год самого элемента.";
            }

            $facts = $row['facts'] ?? null;
            if (!is_array($facts) || count($facts) < 3) {
                $errors[] = "{$label}: нужно не меньше трёх фактов («лишнему факту» нужны три своих).";
                continue;
            }
            $factIds = [];
            $ownGroups = [];
            foreach ($facts as $fact) {
                $factId = is_array($fact) ? ($fact['id'] ?? null) : null;
                $text = trim((string) ($fact['text'] ?? ''));
                if (!is_int($factId) || $text === '') {
                    $errors[] = "{$label}: факт без id или текста.";
                    continue;
                }
                if (isset($factIds[$factId])) {
                    $errors[] = "{$label}: id факта {$factId} встречается дважды.";
                }
                $factIds[$factId] = true;
                if (!in_array($fact['type'] ?? null, self::FACT_TYPES, true)) {
                    $errors[] = "{$label}, факт {$factId}: type — одно из ".implode(', ', self::FACT_TYPES).'.';
                }
                if ($leaks($text)) {
                    $errors[] = "{$label}, факт {$factId}: в тексте стоит год самого элемента.";
                }
                $group = $fact['group'] ?? null;
                if ($group !== null) {
                    $groupSizes[$group] = ($groupSizes[$group] ?? 0) + 1;
                    if (isset($ownGroups[$group])) {
                        $errors[] = "{$label}: два факта в одной группе {$group}.";
                    }
                    $ownGroups[$group] = true;
                }
            }
        }

        foreach ($groupSizes as $group => $size) {
            if ($size < 2) {
                $errors[] = "Группа {$group} состоит из одного факта.";
            }
        }

        foreach ($items as [$kind, $row]) {
            $id = $row['id'] ?? null;
            if (!is_string($id)) {
                continue;
            }

            $parent = $row['parent'] ?? null;
            if ($parent !== null && (($kindOf[$parent] ?? null) !== $kind || $parent === $id)) {
                $errors[] = "«{$id}»: родителя «{$parent}» нет среди элементов того же вида.";
            }
            foreach ((array) ($row['confusable_with'] ?? []) as $other) {
                if (($kindOf[$other] ?? null) !== $kind) {
                    $errors[] = "«{$id}»: в confusable_with указан «{$other}», которого нет среди элементов того же вида.";
                }
            }
            foreach ((array) ($row['reigns'] ?? []) as $reign) {
                if (($kindOf[$reign] ?? null) !== HistEvent::KIND_REIGN) {
                    $errors[] = "«{$id}»: в reigns указано «{$reign}» — такого правления в файле нет.";
                }
            }
            $notes = $row['pair_notes'] ?? [];
            if (!is_array($notes) || ($notes !== [] && array_is_list($notes))) {
                $errors[] = "«{$id}»: pair_notes должен быть объектом вида {\"id другого элемента\": \"заметка\"}.";
            } else {
                foreach ($notes as $other => $note) {
                    if (!isset($kindOf[$other]) || $other === $id) {
                        $errors[] = "«{$id}»: заметка в pair_notes ссылается на «{$other}» — такого другого элемента нет.";
                    } elseif (!is_string($note) || trim($note) === '') {
                        $errors[] = "«{$id}»: пустая заметка в pair_notes для «{$other}».";
                    }
                }
            }
        }

        foreach (self::orderErrors($data['order'] ?? null, array_keys($kindOf)) as $error) {
            $errors[] = $error;
        }

        return array_values(array_unique($errors));
    }

    /**
     * Поле order — порядок изучения, решение автора: ступени от главного к
     * второстепенному. Если оно есть, в нём должен быть каждый элемент ровно
     * один раз: иначе забытый элемент тихо уехал бы в конец или в начало.
     *
     * @param  string[]  $ids
     * @return string[]
     */
    private static function orderErrors(mixed $order, array $ids): array
    {
        if ($order === null) {
            return [];
        }
        if (!is_array($order) || !array_is_list($order)) {
            return ['order должен быть списком ступеней вида {"stage": "название", "ids": [...]}.'];
        }

        $errors = [];
        $seen = [];
        foreach ($order as $i => $stage) {
            if (!is_array($stage) || !is_array($stage['ids'] ?? null)) {
                $errors[] = 'order, ступень №'.($i + 1).': нет списка ids.';
                continue;
            }
            foreach ($stage['ids'] as $id) {
                if (!in_array($id, $ids, true)) {
                    $errors[] = "order: «{$id}» — такого элемента в файле нет.";
                } elseif (isset($seen[$id])) {
                    $errors[] = "order: «{$id}» стоит дважды.";
                }
                $seen[$id] = true;
            }
        }
        foreach ($ids as $id) {
            if (!isset($seen[$id])) {
                $errors[] = "order: нет элемента «{$id}».";
            }
        }

        return $errors;
    }

    /**
     * Что изменится при заливке — без записи в базу.
     *
     * @return array{items: array{added: string[], changed: string[], removed: string[]}, facts: array{added: int, changed: int, removed: int}}
     */
    public function plan(array $data): array
    {
        $existing = HistEvent::query()->with(['confusables:id,slug', 'reigns:id,slug'])->get()->keyBy('slug');
        $existingFacts = HistFact::query()->get()->keyBy('key');
        $plan = [
            'items' => ['added' => [], 'changed' => [], 'removed' => []],
            'facts' => ['added' => 0, 'changed' => 0, 'removed' => 0],
        ];
        $seenSlugs = [];
        $seenKeys = [];

        foreach (self::items($data) as [$kind, $row]) {
            $slug = $row['id'];
            $seenSlugs[$slug] = true;
            $item = $existing->get($slug);
            $changed = $item && (!$item->is_active || $this->snapshot($item) != $this->normalize($kind, $row));
            if (!$item) {
                $plan['items']['added'][] = $slug;
            }

            foreach ($row['facts'] as $position => $fact) {
                $key = self::factKey($slug, $fact['id']);
                $seenKeys[$key] = true;
                $current = $existingFacts->get($key);
                if (!$current) {
                    $plan['facts']['added']++;
                    $changed = $changed || (bool) $item;
                } elseif (!$current->is_active || $current->text !== trim($fact['text']) || $current->type !== $fact['type']
                    || $current->group !== ($fact['group'] ?? null) || (int) $current->position !== $position + 1) {
                    $plan['facts']['changed']++;
                    $changed = $changed || (bool) $item;
                }
            }
            if ($changed) {
                $plan['items']['changed'][] = $slug;
            }
        }

        foreach ($existing as $slug => $item) {
            if ($item->is_active && !isset($seenSlugs[$slug])) {
                $plan['items']['removed'][] = $slug;
            }
        }
        foreach ($existingFacts as $key => $fact) {
            if ($fact->is_active && !isset($seenKeys[$key])) {
                $plan['facts']['removed']++;
            }
        }

        return $plan;
    }

    /**
     * Заливает файл. Вызывающий код обязан сначала прогнать validate().
     */
    public function apply(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $plan = $this->plan($data);
            $items = self::items($data);
            $positions = self::positions($data);
            $idBySlug = [];
            $seenKeys = [];

            foreach ($items as [$kind, $row]) {
                $n = $this->normalize($kind, $row);
                $item = HistEvent::updateOrCreate(['slug' => $row['id']], [
                    'kind' => $kind,
                    'title' => $n['title'],
                    'other_names' => $n['other_names'],
                    'summary' => $n['summary'],
                    'date_display' => $n['date_display'],
                    'year_start' => $n['year_start'],
                    'year_end' => $n['year_end'],
                    'era' => $n['era'],
                    'level' => $n['level'],
                    'sort_order' => $this->sortOrder($n['level'], $n['year_start'], $kind, $positions[$row['id']] ?? null),
                    'pair_notes' => $n['pair_notes'] ?: null,
                    'image' => $n['image'],
                    'is_active' => true,
                ]);
                $idBySlug[$row['id']] = $item->id;

                foreach ($row['facts'] as $position => $fact) {
                    $key = self::factKey($row['id'], $fact['id']);
                    $seenKeys[] = $key;
                    HistFact::updateOrCreate(['key' => $key], [
                        'event_id' => $item->id,
                        'position' => $position + 1,
                        'type' => $fact['type'],
                        'text' => trim($fact['text']),
                        'group' => $fact['group'] ?? null,
                        'is_active' => true,
                    ]);
                }
            }

            // Второй проход: связи ссылаются на элементы, которые в первом
            // проходе могли быть ещё не созданы.
            foreach ($items as [, $row]) {
                $item = HistEvent::find($idBySlug[$row['id']]);
                $parent = $row['parent'] ?? null;
                $item->parent_id = $parent !== null ? $idBySlug[$parent] : null;
                $item->save();

                $item->confusables()->sync(array_map(fn ($s) => $idBySlug[$s], $row['confusable_with'] ?? []));
                $item->reigns()->sync(array_map(fn ($s) => $idBySlug[$s], $row['reigns'] ?? []));
            }

            HistEvent::query()->whereNotIn('id', array_values($idBySlug))->update(['is_active' => false]);
            HistFact::query()->whereNotIn('key', $seenKeys)->update(['is_active' => false]);

            return $plan;
        });
    }

    /**
     * Порядок изучения: уровень, затем место в списке order (от главного к
     * второстепенному). Файл без order — по году начала, событие раньше
     * правления того же года.
     */
    public function sortOrder(int $level, int $yearStart, string $kind, ?int $position = null): int
    {
        if ($position !== null) {
            return $level * 100_000 + $position;
        }

        return $level * 100_000 + $yearStart * 10 + ($kind === HistEvent::KIND_REIGN ? 1 : 0);
    }

    /** @return array<string, int> id элемента => место в order, с 1 */
    private static function positions(array $data): array
    {
        $positions = [];
        foreach ($data['order'] ?? [] as $stage) {
            foreach ($stage['ids'] as $id) {
                $positions[$id] = count($positions) + 1;
            }
        }

        return $positions;
    }

    public static function factKey(string $slug, int $factId): string
    {
        return $slug.'__f'.$factId;
    }

    /** @return array<int, array{0: string, 1: array}> [вид, строка файла] */
    private static function items(array $data): array
    {
        $items = [];
        foreach ($data['events'] ?? [] as $row) {
            $items[] = [HistEvent::KIND_EVENT, $row];
        }
        foreach ($data['reigns'] ?? [] as $row) {
            $items[] = [HistEvent::KIND_REIGN, $row];
        }

        return $items;
    }

    private function normalize(string $kind, array $row): array
    {
        $confusable = array_values($row['confusable_with'] ?? []);
        sort($confusable);
        $reigns = array_values($row['reigns'] ?? []);
        sort($reigns);

        return [
            'kind' => $kind,
            'title' => trim($row['title']),
            'other_names' => array_values($row['other_names'] ?? []),
            'summary' => ($row['summary'] ?? null) !== null ? trim($row['summary']) : null,
            'date_display' => trim($row['date']['display']),
            'year_start' => (int) $row['date']['start'],
            'year_end' => $row['date']['end'] ?? null,
            'era' => (int) $row['era'],
            'level' => (int) $row['level'],
            'pair_notes' => $this->sortedNotes($row['pair_notes'] ?? []),
            'image' => $row['image'] ?? null,
            'parent' => $row['parent'] ?? null,
            'confusable_with' => $confusable,
            'reigns' => $reigns,
        ];
    }

    private function snapshot(HistEvent $item): array
    {
        $confusable = $item->confusables->pluck('slug')->all();
        sort($confusable);
        $reigns = $item->reigns->pluck('slug')->all();
        sort($reigns);

        return [
            'kind' => $item->kind,
            'title' => $item->title,
            'other_names' => array_values($item->other_names ?? []),
            'summary' => $item->summary,
            'date_display' => $item->date_display,
            'year_start' => (int) $item->year_start,
            'year_end' => $item->year_end === null ? null : (int) $item->year_end,
            'era' => (int) $item->era,
            'level' => (int) $item->level,
            'pair_notes' => $this->sortedNotes($item->pair_notes ?? []),
            'image' => $item->image,
            'parent' => $item->parent_id ? HistEvent::find($item->parent_id)?->slug : null,
            'confusable_with' => $confusable,
            'reigns' => $reigns,
        ];
    }

    private function sortedNotes(array $notes): array
    {
        $notes = array_map(fn ($note) => trim((string) $note), $notes);
        ksort($notes);

        return $notes;
    }
}
