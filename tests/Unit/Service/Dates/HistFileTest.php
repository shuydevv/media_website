<?php

namespace Tests\Unit\Service\Dates;

use App\Service\Dates\HistImporter;
use PHPUnit\Framework\TestCase;

/**
 * Проверки самого файла базы тренажёра дат и правил его приёмки — без БД.
 */
class HistFileTest extends TestCase
{
    private function realFile(): array
    {
        return json_decode(file_get_contents(__DIR__.'/../../../../docs/history/events_base.json'), true);
    }

    private function item(string $id, array $overrides = []): array
    {
        return array_merge([
            'id' => $id,
            'title' => 'Событие',
            'other_names' => [],
            'date' => ['display' => '1709', 'start' => 1709, 'end' => 1709],
            'era' => 4,
            'level' => 1,
            'summary' => 'Описание.',
            'parent' => null,
            'confusable_with' => [],
            'pair_notes' => [],
            'facts' => [
                ['id' => 1, 'type' => 'detail', 'text' => 'факт один', 'group' => null],
                ['id' => 2, 'type' => 'person', 'text' => 'факт два', 'group' => null],
                ['id' => 3, 'type' => 'result', 'text' => 'факт три', 'group' => null],
            ],
        ], $overrides);
    }

    private function file(array $events, array $reigns = []): array
    {
        return ['eras' => ['4' => 'Империя XVIII в.'], 'events' => $events, 'reigns' => $reigns];
    }

    /** @test */
    public function the_shipped_file_is_accepted()
    {
        $this->assertSame([], HistImporter::validate($this->realFile()));
    }

    /** @test */
    public function the_shipped_file_has_the_base_level()
    {
        $data = $this->realFile();

        $this->assertCount(100, $data['events']);
        $this->assertGreaterThan(50, count($data['reigns']));
    }

    /** @test */
    public function a_valid_minimal_file_is_accepted()
    {
        $this->assertSame([], HistImporter::validate($this->file([$this->item('a'), $this->item('b', ['confusable_with' => ['a']])])));
    }

    /** @test */
    public function broken_files_are_rejected_with_a_reason()
    {
        $cases = [
            'дубль id' => $this->file([$this->item('a'), $this->item('a')]),
            'мало фактов' => $this->file([$this->item('a', ['facts' => [['id' => 1, 'type' => 'detail', 'text' => 'x']]])]),
            'плохой период' => $this->file([$this->item('a', ['era' => 9])]),
            'конец раньше начала' => $this->file([$this->item('a', ['date' => ['display' => 'x', 'start' => 1709, 'end' => 1700]])]),
            'событие без конца' => $this->file([$this->item('a', ['date' => ['display' => 'x', 'start' => 1709, 'end' => null]])]),
            'год в факте' => $this->file([$this->item('a', ['facts' => [
                ['id' => 1, 'type' => 'detail', 'text' => 'это было в 1709 году'],
                ['id' => 2, 'type' => 'detail', 'text' => 'два'],
                ['id' => 3, 'type' => 'detail', 'text' => 'три'],
            ]])]),
            'год в названии' => $this->file([$this->item('a', ['title' => 'Битва 1709 года'])]),
            'путают с несуществующим' => $this->file([$this->item('a', ['confusable_with' => ['ghost']])]),
            'путают событие с правлением' => $this->file([$this->item('a', ['confusable_with' => ['r']])], [$this->item('r')]),
            'reigns ссылается на событие' => $this->file([$this->item('a', ['reigns' => ['b']]), $this->item('b')]),
            'группа из одного факта' => $this->file([$this->item('a', ['facts' => [
                ['id' => 1, 'type' => 'detail', 'text' => 'один', 'group' => 'g'],
                ['id' => 2, 'type' => 'detail', 'text' => 'два'],
                ['id' => 3, 'type' => 'detail', 'text' => 'три'],
            ]])]),
        ];

        foreach ($cases as $name => $data) {
            $this->assertNotSame([], HistImporter::validate($data), "Не отклонён случай «{$name}»");
        }
    }

    /** @test */
    public function the_study_order_must_list_every_item_once()
    {
        $items = [$this->item('a'), $this->item('b')];
        $with = fn (array $ids) => $this->file($items) + ['order' => [['stage' => 'Главное', 'ids' => $ids]]];

        $this->assertSame([], HistImporter::validate($with(['b', 'a'])));
        $this->assertNotSame([], HistImporter::validate($with(['a'])), 'забыт элемент');
        $this->assertNotSame([], HistImporter::validate($with(['a', 'b', 'a'])), 'дубль');
        $this->assertNotSame([], HistImporter::validate($with(['a', 'b', 'ghost'])), 'чужой id');
    }

    /** @test */
    public function the_study_order_beats_the_year()
    {
        $importer = new HistImporter();

        $this->assertLessThan(
            $importer->sortOrder(1, 862, 'event', 2),
            $importer->sortOrder(1, 1709, 'event', 1)
        );
        // Уровень по-прежнему главнее порядка.
        $this->assertLessThan(
            $importer->sortOrder(2, 1709, 'event', 1),
            $importer->sortOrder(1, 862, 'event', 160)
        );
    }

    /** @test */
    public function a_current_reign_may_have_no_end()
    {
        $reign = $this->item('r', ['date' => ['display' => 'с 2012', 'start' => 2012, 'end' => null]]);

        $this->assertSame([], HistImporter::validate($this->file([$this->item('a')], [$reign])));
    }
}
