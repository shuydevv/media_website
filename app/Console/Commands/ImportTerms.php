<?php

namespace App\Console\Commands;

use App\Service\Terms\TermImporter;
use Illuminate\Console\Command;

class ImportTerms extends Command
{
    protected $signature = 'terms:import
        {path=docs/terms/egkh_terms_18.json : Путь к JSON с терминами (от корня приложения или абсолютный)}
        {--dry-run : Только показать, что изменится, без записи в базу}';

    protected $description = 'Заливает базу терминов тренажёра (задание 18) из JSON; прогресс учеников не трогает';

    public function handle(TermImporter $importer): int
    {
        $path = $this->argument('path');
        if (!is_file($path)) {
            $path = base_path($path);
        }
        if (!is_file($path)) {
            $this->error('Файл не найден: '.$this->argument('path'));

            return self::FAILURE;
        }

        $rows = json_decode((string) file_get_contents($path), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Файл не читается как JSON: '.json_last_error_msg());

            return self::FAILURE;
        }

        $errors = TermImporter::validate($rows);
        if ($errors) {
            $this->error('Файл не принят, ошибок: '.count($errors));
            foreach ($errors as $error) {
                $this->line('  '.$error);
            }

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $plan = $dryRun ? $importer->plan($rows) : $importer->apply($rows);

        $this->info($dryRun ? 'Пробный прогон, база не менялась:' : 'Залито:');
        $this->line(sprintf(
            '  термины: новых %d, изменённых %d, скрытых %d',
            count($plan['terms']['added']), count($plan['terms']['changed']), count($plan['terms']['removed'])
        ));
        $this->line(sprintf(
            '  признаки: новых %d, изменённых %d, скрытых %d',
            $plan['features']['added'], $plan['features']['changed'], $plan['features']['removed']
        ));

        foreach (['changed' => 'Изменены', 'removed' => 'Скрыты'] as $key => $title) {
            if ($plan['terms'][$key]) {
                $this->line("  {$title}: ".implode(', ', $plan['terms'][$key]));
            }
        }

        return self::SUCCESS;
    }
}
