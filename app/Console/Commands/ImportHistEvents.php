<?php

namespace App\Console\Commands;

use App\Service\Dates\HistImporter;
use Illuminate\Console\Command;

class ImportHistEvents extends Command
{
    protected $signature = 'dates:import
        {path=docs/history/events_base.json : Путь к JSON с событиями и правлениями (от корня приложения или абсолютный)}
        {--dry-run : Только показать, что изменится, без записи в базу}';

    protected $description = 'Заливает базу тренажёра дат по истории из JSON; прогресс учеников не трогает';

    public function handle(HistImporter $importer): int
    {
        $path = $this->argument('path');
        if (!is_file($path)) {
            $path = base_path($path);
        }
        if (!is_file($path)) {
            $this->error('Файл не найден: '.$this->argument('path'));

            return self::FAILURE;
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Файл не читается как JSON: '.json_last_error_msg());

            return self::FAILURE;
        }

        $errors = HistImporter::validate($data);
        if ($errors) {
            $this->error('Файл не принят, ошибок: '.count($errors));
            foreach ($errors as $error) {
                $this->line('  '.$error);
            }

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $plan = $dryRun ? $importer->plan($data) : $importer->apply($data);

        $this->info($dryRun ? 'Пробный прогон, база не менялась:' : 'Залито:');
        $this->line(sprintf(
            '  события и правления: новых %d, изменённых %d, скрытых %d',
            count($plan['items']['added']), count($plan['items']['changed']), count($plan['items']['removed'])
        ));
        $this->line(sprintf(
            '  факты: новых %d, изменённых %d, скрытых %d',
            $plan['facts']['added'], $plan['facts']['changed'], $plan['facts']['removed']
        ));
        foreach (['changed' => 'Изменены', 'removed' => 'Скрыты'] as $key => $title) {
            if ($plan['items'][$key]) {
                $this->line("  {$title}: ".implode(', ', $plan['items'][$key]));
            }
        }

        return self::SUCCESS;
    }
}
