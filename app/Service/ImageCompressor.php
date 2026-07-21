<?php

namespace App\Service;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Сжатие загружаемых изображений через GD — расширение уже есть в
 * окружении, отдельный пакет не нужен. Всегда пережимаем в JPEG (даже
 * PNG/WebP — прозрачность всё равно не нужна для фото-контента этого
 * приложения), разница между режимами — насколько агрессивно.
 */
class ImageCompressor
{
    public function __construct(
        private ?int $maxDimension,
        private int $quality,
    ) {
    }

    /**
     * Для аватаров: показываются максимум ~64px, качество не важно —
     * режем до 400px и сильно пережимаем.
     */
    public static function forAvatars(): self
    {
        return new self(maxDimension: 400, quality: 70);
    }

    /**
     * Для остального контента (уроки, курсы, упражнения, посты,
     * шпаргалки, задания в домашках) — разрешение не трогаем, только
     * поднимаем JPEG-качество так, чтобы файл похудел за счёт лишних
     * данных кодирования, а не за счёт видимой детализации.
     */
    public static function forContent(): self
    {
        return new self(maxDimension: null, quality: 85);
    }

    /** Сжимает и сразу сохраняет на диск, возвращает путь (для замены на месте старого ->store()). */
    public function storeAs(UploadedFile $file, string $directory, string $disk = 'public'): string
    {
        $filename = trim($directory, '/') . '/' . Str::random(40) . '.jpg';
        Storage::disk($disk)->put($filename, $this->compress($file));

        return $filename;
    }

    /** Возвращает сырые байты сжатого JPEG. */
    public function compress(UploadedFile $file): string
    {
        // Декодирование в truecolor-битмап современного фото с телефона
        // (это ДО ресайза, даже если ресайза в итоге не будет) само по
        // себе может занимать сотни МБ — размер входного JPEG-файла тут
        // ни при чём, только пиксельные размеры. На дефолтном memory_limit
        // это реально падает с "Allowed memory size exhausted" на больших
        // фото. Поднимаем лимит только на время этой операции; ini_set
        // тихо возвращает false, если запрещён хостингом — тогда просто
        // останемся на прежнем лимите.
        $previousLimit = ini_get('memory_limit');
        ini_set('memory_limit', '512M');

        try {
            return $this->doCompress($file);
        } finally {
            ini_set('memory_limit', $previousLimit);
        }
    }

    private function doCompress(UploadedFile $file): string
    {
        $source = $this->readImage($file);
        $source = $this->applyExifOrientation($source, $file);

        $width = imagesx($source);
        $height = imagesy($source);

        if ($this->maxDimension !== null) {
            $ratio = min(1, $this->maxDimension / max($width, $height));
        } else {
            $ratio = 1; // без ресайза — оставляем исходные пиксельные размеры
        }
        $newWidth = max(1, (int) round($width * $ratio));
        $newHeight = max(1, (int) round($height * $ratio));

        $resized = imagecreatetruecolor($newWidth, $newHeight);
        // Белый фон — прозрачность PNG/WebP всё равно не переживёт конвертацию в JPEG.
        $white = imagecolorallocate($resized, 255, 255, 255);
        imagefill($resized, 0, 0, $white);
        imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        ob_start();
        imagejpeg($resized, null, $this->quality);
        $bytes = ob_get_clean();

        imagedestroy($source);
        imagedestroy($resized);

        return $bytes;
    }

    /** @return \GdImage */
    private function readImage(UploadedFile $file)
    {
        $path = $file->getRealPath();
        $mime = (string) $file->getMimeType();

        return match (true) {
            str_contains($mime, 'png') => imagecreatefrompng($path),
            str_contains($mime, 'webp') => imagecreatefromwebp($path),
            str_contains($mime, 'gif') => imagecreatefromgif($path),
            default => imagecreatefromjpeg($path),
        };
    }

    /**
     * Разворачиваем по EXIF Orientation — иначе фото, снятые на телефон
     * в портретной ориентации, после ресайза часто оказываются набок.
     *
     * @return \GdImage
     */
    private function applyExifOrientation($image, UploadedFile $file)
    {
        $mime = (string) $file->getMimeType();
        if (!str_contains($mime, 'jpeg') || !function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($file->getRealPath());
        $orientation = $exif['Orientation'] ?? 1;

        return match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }
}
