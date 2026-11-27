<?php

namespace App\Service\LiveChat;

use RuntimeException;

/** Действие в чате не разрешено — текст для ученика + код для браузера. */
class ChatRefused extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message, public readonly array $extra = [])
    {
        parent::__construct($message);
    }

    public function toResponse(int $status = 422)
    {
        return response()->json(['error' => $this->reason, 'message' => $this->getMessage()] + $this->extra, $status);
    }
}
