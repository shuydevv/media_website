<?php

namespace App\Console\Commands;

use App\Service\Bank\BankImporter;
use Illuminate\Console\Command;

class ImportBank extends Command
{
    protected $signature = 'bank:import
        {path? : Путь к JSON (по умолчанию config task_trainer.path)}
        {--category= : id категории (по умолчанию — обществознание по названию)}
        {--dry-run : Только проверить файл и показать, что изменится}';

    protected $description = 'Заливает задания ЕГЭ из docs/bank/tasks.json в банк; правки в админке не перетирает';

    public function handle(BankImporter $importer): int
    {
        $path = $this->argument('path') ?? config('task_trainer.path');
        if (! is_file($path)) {
            $path = base_path($path);
        }
        if (! is_file($path)) {
            $this->error('Файл не найден: '.($this->argument('path') ?? config('task_trainer.path')));

            return self::FAILURE;
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Файл не читается как JSON: '.json_last_error_msg());

            return self::FAILURE;
        }

        $errors = BankImporter::validate($data);
        if ($errors) {
            $this->error('Файл не принят, ошибок: '.count($errors));
            foreach (array_slice($errors, 0, 50) as $error) {
                $this->line('  '.$error);
            }

            return self::FAILURE;
        }

        $category = BankImporter::findCategory($this->option('category') ? (int) $this->option('category') : null);
        if (! $category) {
            $this->error('Не нашлась категория обществознания. Укажите её id: --category=ID');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $stats = $dryRun
            ? $importer->plan($data, $category)
            : $importer->apply($data, $category, dirname($path).DIRECTORY_SEPARATOR.'images');

        $this->info(($dryRun ? 'Пробный прогон, база не менялась' : 'Залито').' — категория «'.$category->title.'»:');
        $this->line(sprintf(
            '  новых %d, изменённых %d, без изменений %d, правил автор (не тронуты) %d, скрыто (нет в файле) %d',
            $stats['added'], $stats['changed'], $stats['kept'], $stats['edited'], $stats['hidden']
        ));
        $this->line(sprintf('  в тренажёре — %d; создано критериев номеров — %d', $stats['active'], $stats['criteria']));

        return self::SUCCESS;
    }
}
