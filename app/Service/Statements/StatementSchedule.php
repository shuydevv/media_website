<?php

namespace App\Service\Statements;

use DateTimeImmutable;

/**
 * Когда вернуть суждение на повтор. Чистая арифметика без БД.
 *
 *  - новое суждение, верно с первого раза — не возвращаем (due_on = null):
 *    суждений тысячи, гонять угаданное незачем; оно вернётся, только когда
 *    новые в блоке кончатся;
 *  - любая ошибка или «Не знаю» — шаг 1, завтра;
 *  - верно на повторе — шаг выше; верно на последнем шаге — усвоено
 *    (due_on = null).
 *
 * Даты — 'Y-m-d', календарные дни.
 */
class StatementSchedule
{
    /** @param array<int, int> $intervals шаг => дней, шаги с 1 подряд */
    public function __construct(private array $intervals)
    {
        ksort($this->intervals);
    }

    public static function fromConfig(): self
    {
        return new self(config('statements.intervals'));
    }

    /**
     * @param  ?int  $step  текущий шаг (null — суждение ученику ещё не попадалось)
     * @param  ?string  $dueOn  текущий срок (null — не на повторе)
     * @return array{step: int, due_on: ?string}
     */
    public function after(?int $step, ?string $dueOn, bool $correct, string $today): array
    {
        if (! $correct) {
            return ['step' => 1, 'due_on' => $this->dueOn(1, $today)];
        }

        // Не на повторе (новое или уже усвоенное) — повторять не нужно.
        if ($step === null || $dueOn === null) {
            return ['step' => $step ?? 0, 'due_on' => null];
        }

        $max = (int) array_key_last($this->intervals);
        if ($step >= $max) {
            return ['step' => $max, 'due_on' => null];
        }

        $next = max(1, $step) + 1;

        return ['step' => $next, 'due_on' => $this->dueOn($next, $today)];
    }

    private function dueOn(int $step, string $today): string
    {
        return (new DateTimeImmutable($today))
            ->modify('+'.$this->intervals[$step].' days')
            ->format('Y-m-d');
    }
}
