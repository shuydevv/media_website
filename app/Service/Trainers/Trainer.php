<?php

namespace App\Service\Trainers;

use App\Models\User;

/**
 * Тренажёр платформы, как его видит дашборд: открыт ли ученику, сколько в
 * нём дел и в какой он ситуации. Сам тренажёр живёт в своём разделе
 * (тренажёр терминов — App\Service\Terms\*), здесь только «витрина».
 * Что при этом скажет рыба, решает App\Service\Trainers\FishSpeech —
 * тренажёр сообщает только ситуацию и свои слова. Новые тренажёры
 * регистрируются в config/trainers.php.
 */
interface Trainer
{
    // Ситуации тренажёра — ключи config('fish_speech.situations').
    public const UNFINISHED = 'unfinished';     // сессия начата и не закончена

    public const OVERDUE = 'overdue';           // повторение ждёт со вчера и раньше

    public const REVIEW = 'review';             // повторение на сегодня

    public const NEW = 'new';                   // есть новое для изучения

    public const NEW_NEAR_LEVEL = 'new_near_level'; // то же, и следующий уровень близко

    public const DONE = 'done';                 // на сегодня всё

    public function title(): string;

    public function url(): string;

    public function availableFor(User $user): bool;

    /**
     * @return array{pending: int, situation: string, count: int, next: ?int}
     *   pending — сколько дел ждёт сегодня (счётчик на кнопке «Тренажёры»);
     *   situation — одна из констант выше; count — сколько элементов в этой
     *   ситуации; next — для DONE: сколько будет на повторении завтра (null —
     *   завтра повторять нечего).
     */
    public function statusFor(User $user): array;

    /**
     * Слова тренажёра для фраз рыбы: формы для чисел (1 / 2 / 5) и
     * множественное без числа.
     *
     * @return array{what: string[], new_what: string[], items: string, new_items: string}
     *   например: ['термин', 'термина', 'терминов'], ['новый термин', …],
     *   'термины', 'новые термины'.
     */
    public function words(): array;
}
