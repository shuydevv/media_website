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

    protected $description = 'Проверить банки вопросов диагностики (docs/diagnostic/questions.json и questions_beginner.json)';

    public function handle(): int
    {
        $blocks = array_keys(config('diagnostic.blocks'));
        $characters = array_keys(config('diagnostic.characters'));
        $total = 0;
        $ids = [];

        // Основной и начальный банки — свои правила, но id общие для лога
        // ответов: совпадать не должны.
        foreach (['bank_path' => false, 'basic.bank_path' => true] as $key => $basic) {
            $path = config("diagnostic.{$key}");
            $this->line(basename($path).':');
            try {
                $bank = QuestionBank::fromFile($path);
            } catch (\RuntimeException $e) {
                $this->error($e->getMessage().' — файл не найден или это не JSON с полем questions.');

                return self::FAILURE;
            }

            $errors = $bank->validate($blocks, $characters, $basic);
            foreach (array_intersect(array_keys($bank->all()), $ids) as $id) {
                $errors[] = "«{$id}»: такой id уже есть в основном банке";
            }
            $ids = array_merge($ids, array_keys($bank->all()));
            foreach ($errors as $error) {
                $this->line("  ✗ {$error}");
            }
            $total += count($errors);
        }

        if ($total > 0) {
            $this->error("Ошибок: {$total}");

            return self::FAILURE;
        }

        $this->info('Банки в порядке.');

        return self::SUCCESS;
    }
}
