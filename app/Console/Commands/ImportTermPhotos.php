<?php

namespace App\Console\Commands;

use App\Models\Term;
use App\Service\Terms\TermPhotoStore;
use Illuminate\Console\Command;
use RuntimeException;

class ImportTermPhotos extends Command
{
    protected $signature = 'terms:photos
        {dir=img_terms/compressed : Папка с фото (от корня приложения или абсолютная)}
        {--missing : Загрузить только тем терминам, у которых фото ещё нет}';

    protected $description = 'Загружает фото терминов тренажёра из папки: файл сопоставляется с термином по имени из базы терминов';

    public function handle(TermPhotoStore $photos): int
    {
        $dir = $this->argument('dir');
        if (!is_dir($dir)) {
            $dir = base_path($dir);
        }
        if (!is_dir($dir)) {
            $this->error('Папка не найдена: '.$this->argument('dir'));

            return self::FAILURE;
        }

        $files = array_values(array_filter(
            scandir($dir),
            fn ($f) => preg_match('/\.(jpe?g|png|webp)$/i', $f)
        ));

        $stored = 0;
        $skipped = 0;
        $unknown = [];
        $broken = [];

        $bar = $this->output->createProgressBar(count($files));
        foreach ($files as $file) {
            $bar->advance();
            $term = $photos->termFor($file);

            if (!$term) {
                $unknown[] = $file;
                continue;
            }
            if ($this->option('missing') && $term->image_path) {
                $skipped++;
                continue;
            }

            try {
                $photos->store($term, $dir.DIRECTORY_SEPARATOR.$file);
                $stored++;
            } catch (RuntimeException $e) {
                $broken[] = $file;
            }
        }
        $bar->finish();
        $this->newLine(2);

        $this->info("Загружено: {$stored}".($skipped ? ", пропущено (фото уже было): {$skipped}" : ''));
        if ($unknown) {
            $this->warn('Нет термина с таким именем файла: '.implode(', ', $unknown));
        }
        if ($broken) {
            $this->warn('Не читаются как картинка: '.implode(', ', $broken));
        }

        $without = Term::active()->whereNull('image_path')->orderBy('sort_order')->pluck('title');
        if ($without->isNotEmpty()) {
            $this->line('Термины без фото ('.$without->count().'): '.$without->implode(', '));
        } else {
            $this->line('Фото есть у всех терминов.');
        }

        return self::SUCCESS;
    }
}
