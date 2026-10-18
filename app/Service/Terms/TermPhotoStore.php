<?php

namespace App\Service\Terms;

use App\Models\Term;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Фото терминов тренажёра: сопоставление файла с термином и сохранение.
 *
 * Файл сопоставляется с термином по имени из поля image базы терминов
 * («lichnost.jpg»), расширение не важно. Хранится под случайным именем в
 * storage/app/public/terms/: исходное имя совпадает с названием термина и в
 * способе с квадратиками выдавало бы ответ прямо в адресе картинки.
 *
 * Рядом кладётся размытая копия — для квадратиков до ответа. Именно отдельный
 * файл, а не CSS-размытие: CSS снимается в инструментах браузера за секунду.
 */
class TermPhotoStore
{
    private const DIR = 'terms';

    // Размытая копия: картинка ужимается до BLUR_TINY пикселей в ширину (от
    // неё остаются только пятна цвета), растягивается обратно до BLUR_WIDTH и
    // дополнительно сглаживается — так не видно ни деталей, ни «пикселей».
    // Сюжет при этом не угадывается, но фото остаётся живым цветным пятном.
    private const BLUR_TINY = 20;

    private const BLUR_WIDTH = 720;

    private const BLUR_PASSES = 4;

    /**
     * Термин, которому предназначен файл, или null, если такого имени в базе
     * нет. Скрытые перезаливкой термины тоже находятся: фото к ним не мешает.
     */
    public function termFor(string $filename): ?Term
    {
        $name = mb_strtolower(pathinfo($filename, PATHINFO_FILENAME));

        return Term::query()->get(['id', 'image'])
            ->first(fn (Term $term) => $term->image !== null
                && mb_strtolower(pathinfo($term->image, PATHINFO_FILENAME)) === $name)
            ?->fresh();
    }

    /**
     * Сохраняет фото термина (заменяя прежнее, если было).
     *
     * @throws RuntimeException если файл не читается как картинка
     */
    public function store(Term $term, string $sourcePath): void
    {
        $image = $this->read($sourcePath);
        $disk = Storage::disk('public');

        $name = self::DIR.'/'.Str::lower(Str::random(32));
        $sharp = $name.'.jpg';
        $blurred = $name.'-b.jpg';

        // JPEG кладём как есть — он уже сжат заранее (img_terms/compress_
        // images.py), пересохранение только ухудшило бы качество. Остальные
        // форматы переводим в JPEG.
        if ($this->isJpeg($sourcePath)) {
            $disk->put($sharp, file_get_contents($sourcePath));
        } else {
            $disk->put($sharp, $this->encode($image, 90));
        }
        $disk->put($blurred, $this->encode($this->blur($image), 70));
        imagedestroy($image);

        $old = array_filter([$term->image_path, $term->image_blur_path]);

        $term->image_path = $sharp;
        $term->image_blur_path = $blurred;
        $term->save();

        if ($old) {
            $disk->delete($old);
        }
    }

    private function read(string $path)
    {
        $data = @file_get_contents($path);
        $image = $data === false ? false : @imagecreatefromstring($data);
        if (!$image) {
            throw new RuntimeException('Файл не читается как картинка.');
        }

        return $image;
    }

    private function isJpeg(string $path): bool
    {
        return @exif_imagetype($path) === IMAGETYPE_JPEG
            || (function_exists('getimagesize') && (@getimagesize($path)[2] ?? null) === IMAGETYPE_JPEG);
    }

    private function blur($image)
    {
        $ratio = imagesy($image) / imagesx($image);

        $tinyHeight = max(1, (int) round(self::BLUR_TINY * $ratio));
        $tiny = imagecreatetruecolor(self::BLUR_TINY, $tinyHeight);
        imagecopyresampled($tiny, $image, 0, 0, 0, 0, self::BLUR_TINY, $tinyHeight, imagesx($image), imagesy($image));

        // Обратно — в два шага с билинейной интерполяцией и сглаживанием на
        // каждом. Проверено на живых картинках: imagecopyresampled() при
        // растяжении рисует квадраты (мозаику), бикубический imagescale()
        // даёт цветные артефакты, а этот вариант — мягкое пятно.
        $mid = imagescale($tiny, self::BLUR_TINY * 4, $tinyHeight * 4, IMG_BILINEAR_FIXED);
        imagedestroy($tiny);
        $this->smooth($mid);

        $outWidth = min(self::BLUR_WIDTH, imagesx($image));
        $out = imagescale($mid, $outWidth, max(1, (int) round($outWidth * $ratio)), IMG_BILINEAR_FIXED);
        imagedestroy($mid);
        $this->smooth($out);

        return $out;
    }

    private function smooth($image): void
    {
        for ($i = 0; $i < self::BLUR_PASSES; $i++) {
            imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);
        }
    }

    private function encode($image, int $quality): string
    {
        ob_start();
        imageinterlace($image, true);
        imagejpeg($image, null, $quality);

        return (string) ob_get_clean();
    }
}
