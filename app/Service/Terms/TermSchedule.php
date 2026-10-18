<?php

namespace App\Service\Terms;

use DateTimeImmutable;

/**
 * Сетка интервального повторения: шаг термина и дата следующего повторения.
 * Чистая арифметика без БД — чтение/запись user_terms делает вызывающий код.
 *
 * Даты — строки 'Y-m-d' без времени: срок считается календарными днями, а
 * не часами (изучил сегодня в любое время — повторение завтра).
 */
class TermSchedule
{
    /** @var array<int, int> шаг => интервал в днях */
    private array $intervals;

    /**
     * @param array<int, int> $intervals шаги с 1 подряд, см. config('terms.intervals')
     */
    public function __construct(array $intervals)
    {
        ksort($intervals);
        $this->intervals = $intervals;
    }

    public static function fromConfig(): self
    {
        return new self(config('terms.intervals'));
    }

    public function maxStep(): int
    {
        return (int) array_key_last($this->intervals);
    }

    /**
     * Термин только что изучен: первый шаг, независимо от ошибок в сессии
     * изучения (они влияют только на корм).
     *
     * @return array{step: int, due_on: string}
     */
    public function afterLearning(string $today): array
    {
        return ['step' => 1, 'due_on' => $this->dueOn(1, $today)];
    }

    /**
     * Итог повторения по первой попытке: успех — шаг вверх (не выше
     * потолка), ошибка — шаг вниз (не ниже первого). Срок в обоих случаях
     * отсчитывается от сегодня, а не от пропущенной даты: опоздание не
     * штрафуется, но и «догонять» пропущенные интервалы не нужно.
     *
     * @return array{step: int, due_on: string}
     */
    public function afterReview(int $step, bool $success, string $today): array
    {
        $step = max(1, min($this->maxStep(), $step));
        $next = $success
            ? min($this->maxStep(), $step + 1)
            : max(1, $step - 1);

        return ['step' => $next, 'due_on' => $this->dueOn($next, $today)];
    }

    /**
     * Способы проверки чередуются от повторения к повторению.
     */
    public function nextMethod(string $method): string
    {
        return $method === 'a' ? 'b' : 'a';
    }

    private function dueOn(int $step, string $today): string
    {
        return (new DateTimeImmutable($today))
            ->modify('+'.$this->intervals[$step].' days')
            ->format('Y-m-d');
    }
}
