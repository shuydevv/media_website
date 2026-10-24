<?php

namespace App\Service\Diagnostic;

use App\Models\DiagnosticAttempt;
use RuntimeException;

/** Ответ не на текущий вопрос — страница получит актуальный экран и продолжит с него. */
class StaleAnswerException extends RuntimeException
{
    public function __construct(public readonly DiagnosticAttempt $attempt)
    {
        parent::__construct('Ответ не на текущий вопрос');
    }
}
