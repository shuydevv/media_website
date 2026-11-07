<?php

namespace App\Console\Commands;

use App\Service\Statements\StatementImporter;
use Illuminate\Console\Command;

class ImportStatements extends Command
{
    protected $signature = 'statements:import
        {path? : Путь к JSON (по умолчанию config statements.path)}
        {--dry-run : Только проверить файл и показать, что изменится}';

    protected $description = 'Заливает базу тренажёра «Верные суждения» из JSON; прогресс учеников не трогает';

    public function handle(StatementImporter $importer): int
    {
        $path = $this->argument('path') ?? config('statements.path');
        if (! is_file($path)) {
            $path = base_path($path);
        }
        if (! is_file($path)) {
            $this->error('Файл не найден: '.($this->argument('path') ?? config('statements.path')));

            return self::FAILURE;
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Файл не читается как JSON: '.json_last_error_msg());

            return self::FAILURE;
        }

        $errors = StatementImporter::validate($data);
        if ($errors) {
            $this->error('Файл не принят, ошибок: '.count($errors));
            foreach (array_slice($errors, 0, 50) as $error) {
                $this->line('  '.$error);
            }

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $plan = $dryRun ? $importer->plan($data) : $importer->apply($data);

        $this->info($dryRun ? 'Пробный прогон, база не менялась:' : 'Залито:');
        $this->line(sprintf(
            '  новых %d, изменённых %d, скрытых (нет в файле) %d; в тренажёре — %d',
            $plan['added'], $plan['changed'], $plan['removed'], $plan['active']
        ));

        return self::SUCCESS;
    }
}
