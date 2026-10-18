<?php

namespace Tests\Unit\Service\Terms;

use App\Service\Terms\DistractorPicker;
use App\Service\Terms\TermImporter;
use PHPUnit\Framework\TestCase;

/**
 * Проверки самого файла базы терминов и правил его приёмки — без БД.
 */
class TermFileTest extends TestCase
{
    private function realFile(): array
    {
        return json_decode(file_get_contents(__DIR__.'/../../../../docs/terms/egkh_terms_18.json'), true);
    }

    private function term(string $slug, array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'slug' => $slug,
            'title' => 'Термин',
            'qualifier' => null,
            'other_names' => [],
            'image' => "{$slug}.jpg",
            'code' => '1.1',
            'priority' => 1,
            'parent' => null,
            'confusable_with' => [],
            'features' => [['id' => "{$slug}__f1", 'text' => 'признак', 'group' => null]],
        ], $overrides);
    }

    /** @test */
    public function the_shipped_file_is_accepted()
    {
        $this->assertSame([], TermImporter::validate($this->realFile()));
    }

    /** @test */
    public function a_valid_minimal_file_is_accepted()
    {
        $this->assertSame([], TermImporter::validate([$this->term('a'), $this->term('b', ['parent' => 'a'])]));
    }

    /** @test */
    public function broken_files_are_rejected_with_a_reason()
    {
        $cases = [
            'не массив' => ['oops'],
            'дубль slug' => [$this->term('a'), $this->term('a', ['features' => [['id' => 'a__f2', 'text' => 'x', 'group' => null]]])],
            'дубль id признака' => [$this->term('a'), $this->term('b', ['features' => [['id' => 'a__f1', 'text' => 'x', 'group' => null]]])],
            'нет признаков' => [$this->term('a', ['features' => []])],
            'плохой код' => [$this->term('a', ['code' => '2'])],
            'плохой приоритет' => [$this->term('a', ['priority' => 4])],
            'нет родителя' => [$this->term('a', ['parent' => 'ghost'])],
            'сам себе родитель' => [$this->term('a', ['parent' => 'a'])],
            'кольцо родителей' => [$this->term('a', ['parent' => 'b']), $this->term('b', ['parent' => 'a'])],
            'неизвестный confusable' => [$this->term('a', ['confusable_with' => ['ghost']])],
            'группа из одного' => [$this->term('a', ['features' => [['id' => 'a__f1', 'text' => 'x', 'group' => 'g1']]])],
            'группа внутри термина' => [$this->term('a', ['features' => [
                ['id' => 'a__f1', 'text' => 'x', 'group' => 'g1'],
                ['id' => 'a__f2', 'text' => 'y', 'group' => 'g1'],
            ]])],
        ];

        foreach ($cases as $name => $rows) {
            $this->assertNotSame([], TermImporter::validate($rows), "Должно быть отклонено: {$name}");
        }
    }

    /**
     * На реальной базе каждому признаку каждого термина хватает трёх
     * дистракторов после всех запретов (parent, group, одинаковый текст) —
     * иначе в квизе где-то оказалось бы меньше четырёх вариантов.
     *
     * @test
     */
    public function every_feature_in_the_shipped_file_gets_three_distractors()
    {
        $rows = $this->realFile();
        $idBySlug = array_column($rows, 'id', 'slug');

        $parentOf = [];
        $pool = [];
        $n = 0;
        foreach ($rows as $row) {
            $parentOf[$row['id']] = $row['parent'] !== null ? $idBySlug[$row['parent']] : null;
            foreach ($row['features'] as $feature) {
                $pool[] = [
                    'id' => ++$n,
                    'term_id' => $row['id'],
                    'section' => (int) $row['code'],
                    'group' => $feature['group'],
                    'text' => $feature['text'],
                ];
            }
        }

        $picker = new DistractorPicker();
        foreach ($rows as $row) {
            $related = $picker->relatedTermIds($parentOf, $row['id']);
            $confusable = array_map(fn ($slug) => $idBySlug[$slug], $row['confusable_with']);

            foreach ($pool as $correct) {
                if ($correct['term_id'] !== $row['id']) {
                    continue;
                }

                $picked = $picker->pick($correct, $related, $confusable, $pool);

                $this->assertCount(3, $picked, "Мало дистракторов: {$row['slug']} — {$correct['text']}");
                foreach ($picked as $distractor) {
                    $this->assertNotContains($distractor['term_id'], array_merge($related, [$row['id']]));
                    if ($correct['group'] !== null) {
                        $this->assertNotSame($correct['group'], $distractor['group']);
                    }
                }
            }
        }
    }

    /**
     * На реальной базе в квизе из четырёх вопросов ни один дистрактор не
     * показывается дважды — иначе у терминов с одним «путаемым» соседом все
     * вопросы шли бы с одинаковыми неверными вариантами.
     *
     * @test
     */
    public function a_quiz_never_repeats_a_distractor_in_the_shipped_file()
    {
        $rows = $this->realFile();
        $idBySlug = array_column($rows, 'id', 'slug');

        $parentOf = [];
        $pool = [];
        $n = 0;
        foreach ($rows as $row) {
            $parentOf[$row['id']] = $row['parent'] !== null ? $idBySlug[$row['parent']] : null;
            foreach ($row['features'] as $feature) {
                $pool[] = [
                    'id' => ++$n,
                    'term_id' => $row['id'],
                    'section' => (int) $row['code'],
                    'group' => $feature['group'],
                    'text' => $feature['text'],
                ];
            }
        }

        $picker = new DistractorPicker();
        foreach ($rows as $row) {
            $related = $picker->relatedTermIds($parentOf, $row['id']);
            $confusable = array_map(fn ($slug) => $idBySlug[$slug], $row['confusable_with']);
            $own = array_slice(array_values(array_filter($pool, fn ($f) => $f['term_id'] === $row['id'])), 0, 4);

            $used = [];
            foreach ($own as $correct) {
                $picked = $picker->pick($correct, $related, $confusable, $pool, 3, $used, 2);
                $ids = array_column($picked, 'id');

                $this->assertCount(3, $ids, "Мало дистракторов: {$row['slug']}");
                $this->assertSame([], array_values(array_intersect($ids, $used)), "Повтор дистрактора: {$row['slug']}");

                $fromConfusable = count(array_filter($picked, fn ($f) => in_array($f['term_id'], $confusable, true)));
                $this->assertLessThanOrEqual(2, $fromConfusable, "Больше двух из confusable: {$row['slug']}");

                $used = array_merge($used, $ids);
            }
        }
    }

    /**
     * «Лишний признак» на реальной базе: для каждого термина находится чужой
     * признак, который не дублирует ни один его собственный (ни по группе, ни
     * по тексту) и не принадлежит родственному по parent термину.
     *
     * @test
     */
    public function every_term_in_the_shipped_file_gets_an_odd_feature()
    {
        $rows = $this->realFile();
        $idBySlug = array_column($rows, 'id', 'slug');

        $parentOf = [];
        $pool = [];
        $n = 0;
        foreach ($rows as $row) {
            $parentOf[$row['id']] = $row['parent'] !== null ? $idBySlug[$row['parent']] : null;
            foreach ($row['features'] as $feature) {
                $pool[] = [
                    'id' => ++$n,
                    'term_id' => $row['id'],
                    'section' => (int) $row['code'],
                    'group' => $feature['group'],
                    'text' => $feature['text'],
                ];
            }
        }

        $picker = new DistractorPicker();
        foreach ($rows as $row) {
            $own = array_values(array_filter($pool, fn ($f) => $f['term_id'] === $row['id']));
            $related = $picker->relatedTermIds($parentOf, $row['id']);

            $odd = $picker->pick(
                ['term_id' => $row['id'], 'section' => (int) $row['code'], 'group' => null, 'text' => ''],
                $related,
                array_map(fn ($slug) => $idBySlug[$slug], $row['confusable_with']),
                $picker->foreignPool($own, $pool),
                1
            );

            $this->assertCount(1, $odd, "Нет лишнего признака: {$row['slug']}");
            $this->assertNotContains($odd[0]['term_id'], array_merge($related, [$row['id']]));
            $this->assertNotContains($odd[0]['text'], array_column($own, 'text'));
            if ($odd[0]['group'] !== null) {
                $this->assertNotContains($odd[0]['group'], array_column($own, 'group'), "Лишний из группы своего признака: {$row['slug']}");
            }
        }
    }

    /** @test */
    public function learning_order_is_priority_then_section_then_topic_then_file_order()
    {
        $importer = new TermImporter();

        $keys = [
            'редкий 1.1' => $importer->sortOrder(2, '1.1', 1),
            'основной 5.20' => $importer->sortOrder(1, '5.20', 174),
            'основной 2.10 позже в файле' => $importer->sortOrder(1, '2.10', 72),
            'основной 2.10' => $importer->sortOrder(1, '2.10', 70),
            'основной 2.3' => $importer->sortOrder(1, '2.3', 41),
        ];
        asort($keys);

        $this->assertSame(
            ['основной 2.3', 'основной 2.10', 'основной 2.10 позже в файле', 'основной 5.20', 'редкий 1.1'],
            array_keys($keys)
        );
    }
}
