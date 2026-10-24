<?php

namespace App\Console\Commands;

use App\Service\Diagnostic\QuestionBank;
use Illuminate\Console\Command;

/**
 * Проверка банка вопросов диагностики (config diagnostic.bank_path) после
 * правки JSON: без ошибок тест гарантированно проходится. Правила —
 * QuestionBank::validate().
 */
class ValidateDiagnosticBank extends Command
{
    protected $signature = 'diagnostic:validate';

    protected $description = 'Проверить банк вопросов диагностики (docs/diagnostic/questions.json)';

    public function handle(): int
    {
        try {
            $bank = QuestionBank::fromFile(config('diagnostic.bank_path'));
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage().' — файл не найден или это не JSON с полем questions.');

            return self::FAILURE;
        }

        $errors = $bank->validate(array_keys(config('diagnostic.blocks')));
        foreach ($errors as $error) {
            $this->line("  ✗ {$error}");
        }

        if ($errors !== []) {
            $this->error('Ошибок: '.count($errors));

            return self::FAILURE;
        }

        $this->info('Банк в порядке.');

        return self::SUCCESS;
    }
}
