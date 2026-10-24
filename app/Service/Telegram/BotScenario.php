<?php

namespace App\Service\Telegram;

use App\Models\TelegramContact;

/**
 * Сценарий одного бота (TelegramBots): что делать с апдейтом и с
 * отложенным шагом. Сценарий работает только со своими контактами
 * (telegram_contacts.bot) — в общей админ-группе апдейты видят все боты,
 * и ответ админа в чужой теме нужно пропустить: написать человеку может
 * только тот бот, которого он запускал.
 */
interface BotScenario
{
    public function handleUpdate(array $update): void;

    /** Одна отправка из RunFunnelStep; условия проверяются в момент отправки. */
    public function runStep(TelegramContact $contact, string $step, array $payload = []): void;

    /**
     * Отложенные шаги: поле *_due_at контакта → [шаг, payload]. Разбирает
     * telegram:send-reminders.
     *
     * @return array<string, array{0: string, 1: array}>
     */
    public function dueSteps(): array;
}
