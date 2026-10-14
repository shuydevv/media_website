<?php

namespace App\Service\Telegram;

use RuntimeException;

/**
 * Ошибка Bot API ({"ok": false, "error_code": …, "description": …}) или сети.
 * Описания у Telegram — свободный текст без кодов подтипа, поэтому типовые
 * случаи распознаются по подстроке (так же делают все известные SDK).
 */
class TelegramApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $errorCode = 0,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, $errorCode);
    }

    /** Пользователь заблокировал бота или удалил аккаунт — писать ему бесполезно. */
    public function isBlockedByUser(): bool
    {
        return $this->errorCode === 403;
    }

    /** Тему в админ-группе удалили руками — надо создать новую. */
    public function isTopicMissing(): bool
    {
        return $this->errorCode === 400
            && (str_contains($this->getMessage(), 'thread not found') || str_contains($this->getMessage(), 'TOPIC_DELETED')
                || str_contains($this->getMessage(), 'TOPIC_ID_INVALID'));
    }

    public function isRateLimited(): bool
    {
        return $this->errorCode === 429;
    }
}
