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
        $path = tempnam(sys_get_temp_dir(), 'skill') . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Не удалось создать ZIP скилла.');
        }

        foreach ($this->files() as $inZip => $source) {
            $zip->addFile($source, self::NAME . '/' . $inZip);
        }
        // Та же версия уходит в каждый файл выгрузки (rules_version) — скилл
        // сверяет и предупреждает, если его скачали до правки правил.
        $zip->addFromString(self::NAME . '/VERSION', $this->version());

        $zip->close();

        return $path;
    }

    /**
     * Короткий хеш содержимого скилла и правил. Не дата файлов: на проде
     * при каждом деплое они клонируются заново и mtime у всех одинаковый.
     */
    public function version(): string
    {
        $hash = hash_init('sha1');
        foreach ($this->files() as $inZip => $source) {
            hash_update($hash, $inZip . "\0" . str_replace("\r\n", "\n", (string) file_get_contents($source)) . "\0");
        }

        return substr(hash_final($hash), 0, 8);
    }

    /** @return array<string, string> путь в ZIP (без папки скилла) → файл на диске, по порядку */
    private function files(): array
    {
        $skillDir = resource_path('ai/skill/' . self::NAME);
        $out = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($skillDir, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $out[str_replace('\\', '/', substr($file->getPathname(), strlen($skillDir) + 1))] = $file->getPathname();
        }
        foreach (glob(resource_path('ai/grading') . '/*.md') as $rules) {
            $out['references/' . basename($rules)] = $rules;
        }
        ksort($out);

        return $out;
    }
}
