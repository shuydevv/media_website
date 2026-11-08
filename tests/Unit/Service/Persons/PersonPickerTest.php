<?php

namespace Tests\Unit\Service\Persons;

use App\Service\Persons\PersonPicker;
use PHPUnit\Framework\TestCase;

class PersonPickerTest extends TestCase
{
    private function person(int $id, string $name, int $era, string $role, string $association, string $description = 'Деятель.', array $related = []): array
    {
        return compact('id', 'name', 'era', 'role', 'association', 'description', 'related');
    }

    /** @test */
    public function same_association_related_and_mentioned_people_are_never_distractors()
    {
        $picker = new PersonPicker();
        $minin = $this->person(1, 'Минин Кузьма', 3, 'M', 'Освобождение Москвы от поляков (1612)');
        $pool = [
            $minin,
            $this->person(2, 'Пожарский Дмитрий', 3, 'M', 'Освобождение Москвы от поляков (1612)'),
            $this->person(3, 'Ляпунов Прокопий', 3, 'M', 'Первое ополчение (1611)', 'Боярин.', [1]),
            $this->person(4, 'Заруцкий Иван', 3, 'M', 'Казачий атаман, соперник Минина'),
            $this->person(5, 'Шеин Михаил', 3, 'M', 'Оборона Смоленска'),
            $this->person(6, 'Скопин-Шуйский Михаил', 3, 'M', 'Разбил войска Тушинского вора'),
            $this->person(7, 'Сусанин Иван', 3, 'H', 'Спас Михаила Романова (1613)'),
        ];

        for ($i = 0; $i < 20; $i++) {
            $ids = array_column($picker->pick($minin, $pool, 3), 'id');
            $this->assertCount(3, $ids);
            $this->assertNotContains(2, $ids); // та же ассоциация
            $this->assertNotContains(3, $ids); // связан (related)
            $this->assertNotContains(4, $ids); // упомянут в тексте
        }
    }

    /** @test */
    public function the_same_era_and_role_come_first()
    {
        $picker = new PersonPicker();
        $target = $this->person(1, 'Кутузов Михаил', 5, 'M', 'Изгнание Наполеона (1812)');
        $pool = [
            $target,
            $this->person(2, 'Багратион Пётр', 5, 'M', 'Командовал Второй армией'),
            $this->person(3, 'Барклай-де-Толли Михаил', 5, 'M', 'Отступление в начале войны'),
            $this->person(4, 'Нахимов Павел', 5, 'M', 'Синоп (1853)'),
            $this->person(5, 'Терешкова Валентина', 7, 'H', 'Первая женщина в космосе'),
            $this->person(6, 'Тохтамыш', 2, 'R', 'Сожжение Москвы (1382)'),
        ];

        $ids = array_column($picker->pick($target, $pool, 3), 'id');
        $this->assertEqualsCanonicalizing([2, 3, 4], $ids);
    }

    /** @test */
    public function one_event_or_one_year_in_two_associations_makes_them_arguable()
    {
        $picker = new PersonPicker();
        $kutuzov = $this->person(1, 'Кутузов Михаил', 5, 'M', 'Бородинская битва и изгнание Наполеона (1812)');
        $bagration = $this->person(2, 'Багратион Пётр', 5, 'M', 'Бородинская битва (1812), смертельно ранен');
        $alexander = $this->person(3, 'Александр I', 5, 'R', 'Победа над Наполеоном (1812)');
        $nakhimov = $this->person(4, 'Нахимов Павел', 5, 'M', 'Синопское сражение (1853)');

        $this->assertTrue($picker->conflicts($kutuzov, $bagration));
        $this->assertTrue($picker->conflicts($bagration, $alexander));
        $this->assertFalse($picker->conflicts($kutuzov, $nakhimov));
    }

    /** @test */
    public function a_roman_numeral_is_part_of_the_name()
    {
        $picker = new PersonPicker();
        $ivan5 = $this->person(1, 'Иван V', 3, 'R', 'Соправитель Петра I');
        $sofia = $this->person(2, 'Царевна Софья Алексеевна', 3, 'R', 'Регентство за малолетних Петра I и Ивана V');
        $ivan3 = $this->person(3, 'Иван III', 2, 'R', 'Стояние на Угре');

        $this->assertTrue($picker->conflicts($ivan5, $sofia));
        $this->assertFalse($picker->conflicts($ivan3, $sofia));
    }

    /** @test */
    public function matching_takes_four_distinct_rows_and_two_extra_people()
    {
        $picker = new PersonPicker();
        $pool = [];
        foreach (range(1, 12) as $id) {
            $pool[] = $this->person($id, 'Фамилия'.$id.' Имя', 5, 'S', 'Дело '.$id);
        }
        // У 1 и 2 одна ассоциация — в одном задании им не место.
        $pool[1]['association'] = $pool[0]['association'];

        $set = $picker->matching([1, 2, 3, 4, 5], $pool);

        $this->assertCount(4, $set['rows']);
        $this->assertCount(6, $set['options']);
        $this->assertFalse(in_array(1, $set['rows'], true) && in_array(2, $set['rows'], true));
        $this->assertSame([], array_diff($set['rows'], $set['options']));
        $this->assertCount(6, array_unique($set['options']));
    }
}
