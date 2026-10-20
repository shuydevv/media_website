<?php

namespace App\Service\Homework;

use RuntimeException;
use ZipArchive;

/**
 * ZIP скилла ege-homework-check для загрузки в Claude Desktop
 * (Настройки → Возможности → Навыки). Собирается на лету из
 * resources/ai/skill/ege-homework-check и правил resources/ai/grading/*.md —
 * правила одни для API и для Desktop, после их правки достаточно скачать
 * скилл заново и перезагрузить его в Claude.
 */
class DesktopGradingSkill
{
    public const NAME = 'ege-homework-check';

    /** Путь к временному ZIP (удалить после отдачи). */
    public function build(): string
    {
        $skillDir = resource_path('ai/skill/' . self::NAME);
        $rulesDir = resource_path('ai/grading');

        $path = tempnam(sys_get_temp_dir(), 'skill') . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Не удалось создать ZIP скилла.');
        }

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($skillDir, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($skillDir) + 1));
            $zip->addFile($file->getPathname(), self::NAME . '/' . $relative);
        }
        foreach (glob($rulesDir . '/*.md') as $rules) {
            $zip->addFile($rules, self::NAME . '/references/' . basename($rules));
        }

        $zip->close();

        return $path;
    }
}
