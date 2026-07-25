<?php

namespace App\Support;

use App\Service\ImageCompressor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

/**
 * Скачивание картинки задания по URL — для JSON-импорта (Admin\Homework\
 * ImportController, Admin\TaskImportController), где взять файл через обычный
 * <input type="file"> неоткуда, а поле image_url в JSON — единственный способ
 * заполнить image_path без ручной пере-загрузки после импорта.
 *
 * Дальше по тому же пути, что и обычная форма: сжатие через тот же
 * ImageCompressor::forContent() и тот же диск/директория — картинка,
 * попавшая через image_url, ничем не отличается от загруженной руками.
 *
 * Ошибка скачивания НЕ должна валить всю строку импорта — по дизайну
 * (см. TaskContentRules) текстовое содержание задания и так самоценно,
 * картинку можно дозагрузить вручную. Поэтому метод возвращает null +
 * причину через $error, а не бросает исключение.
 */
class TaskImageImporter
{
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    // С запасом над лимитом ручной формы (5MB, см. TaskContentRules::rules())
    // — сжатие всё равно уменьшит итоговый файл, лишний повод отклонять смысла нет.
    private const MAX_BYTES = 8 * 1024 * 1024;

    public static function download(string $url, ?string &$error = null): ?string
    {
        if (!preg_match('~^https?://~i', $url)) {
            $error = 'image_url должен начинаться с http:// или https://';
            return null;
        }

        try {
            $response = Http::timeout(10)->get($url);
        } catch (\Throwable $e) {
            $error = 'не удалось скачать изображение (' . $e->getMessage() . ')';
            return null;
        }

        if (!$response->successful()) {
            $error = 'не удалось скачать изображение (HTTP ' . $response->status() . ')';
            return null;
        }

        $body = $response->body();
        if ($body === '' || strlen($body) > self::MAX_BYTES) {
            $error = 'изображение пустое или больше 8MB';
            return null;
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'task_img_');
        file_put_contents($tmpPath, $body);

        $mime = self::detectMime($response->header('Content-Type'), $tmpPath);
        if (!$mime) {
            @unlink($tmpPath);
            $error = 'по ссылке не изображение (ожидались jpg/png/webp/gif)';
            return null;
        }

        $extension = match ($mime) {
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
            default      => 'jpg',
        };
        $name = basename((string) parse_url($url, PHP_URL_PATH)) ?: "image.{$extension}";
        $uploaded = new UploadedFile($tmpPath, $name, $mime, null, true);

        try {
            return ImageCompressor::forContent()->storeAs($uploaded, 'homework_images');
        } catch (\Throwable $e) {
            $error = 'не удалось обработать изображение (' . $e->getMessage() . ')';
            return null;
        } finally {
            @unlink($tmpPath);
        }
    }

    private static function detectMime(?string $contentType, string $tmpPath): ?string
    {
        $mime = $contentType ? strtolower(trim(explode(';', $contentType)[0])) : null;
        if ($mime && in_array($mime, self::ALLOWED_MIME, true)) {
            return $mime;
        }

        // Content-Type сервера бывает врёт/отсутствует (например, отдаёт
        // generic application/octet-stream) — подстраховка по сигнатуре байт.
        $detected = @getimagesize($tmpPath);
        $mime = $detected['mime'] ?? null;

        return in_array($mime, self::ALLOWED_MIME, true) ? $mime : null;
    }
}
