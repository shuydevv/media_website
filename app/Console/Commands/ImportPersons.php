<?php

namespace App\Console\Commands;

use App\Service\Persons\PersonImporter;
use Illuminate\Console\Command;

class ImportPersons extends Command
{
    protected $signature = 'persons:import
        {path=docs/personalities/personalities.json : Путь к JSON с личностями (от корня приложения или абсолютный)}
        {--dry-run : Только показать, что изменится, без записи в базу}';

    protected $description = 'Заливает базу тренажёра личностей из JSON и копирует портреты; прогресс учеников не трогает';

    public function handle(PersonImporter $importer): int
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

        $errors = PersonImporter::validate($data);
        if ($errors) {
            $this->error('Файл не принят, ошибок: '.count($errors));
            foreach ($errors as $error) {
                $this->line('  '.$error);
            }

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $plan = $dryRun ? $importer->plan($data) : $importer->apply($data, dirname($path));

        $this->info($dryRun ? 'Пробный прогон, база не менялась:' : 'Залито:');
        $this->line(sprintf(
            '  личности: новых %d, изменённых %d, скрытых %d%s',
            count($plan['added']), count($plan['changed']), count($plan['removed']),
            $dryRun ? '' : ', портретов скопировано '.$plan['images']
        ));
        foreach (['changed' => 'Изменены', 'removed' => 'Скрыты'] as $key => $title) {
            if ($plan[$key]) {
                $this->line("  {$title}: ".implode(', ', $plan[$key]));
            }
        }

        return self::SUCCESS;
    }
}
