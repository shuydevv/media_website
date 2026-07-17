<?php

namespace App\Console\Commands;

use App\Models\Submission;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;

class ClearSubmissions extends Command
{
    use ConfirmableTrait;

    protected $signature = 'submissions:clear {--force : Выполнить без подтверждения}';

    protected $description = 'Удалить все попытки сдачи домашек (таблица submissions)';

    public function handle(): int
    {
        if (!$this->confirmToProceed('Это удалит ВСЕ попытки сдачи домашек безвозвратно.')) {
            return self::FAILURE;
        }

        $count = Submission::count();
        Submission::query()->delete();

        $this->info("Удалено попыток: {$count}");

        return self::SUCCESS;
    }
}
