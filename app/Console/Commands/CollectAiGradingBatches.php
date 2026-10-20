<?php

namespace App\Console\Commands;

use App\Service\Homework\ClaudeBatchGrader;
use Illuminate\Console\Command;

/** Раскладывает готовые результаты пачек ИИ-проверки по черновикам (см. ClaudeBatchGrader). */
class CollectAiGradingBatches extends Command
{
    protected $signature = 'ai:collect-grading-batches';

    protected $description = 'Забрать результаты завершившихся пачек ИИ-проверки домашек';

    public function handle(ClaudeBatchGrader $batches): int
    {
        $done = $batches->collect();
        $this->info("Обработано пачек: {$done}");

        return self::SUCCESS;
    }
}
