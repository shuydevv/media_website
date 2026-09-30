<?php

namespace App\Support\PostContent;

use App\Models\Image;
use Illuminate\Support\Collection;

final class PostImages
{
    /**
     * Находит картинку поста для компонента в контенте.
     *
     * src="kalka-1" — по имени загруженного файла (с расширением или без,
     * без учёта регистра). Это основной способ: имя видно в админке и не
     * зависит от порядка загрузки.
     *
     * img="0" — старый способ, порядковый номер среди картинок поста по id.
     * Оставлен для уже написанных постов.
     */
    public static function find(Collection $images, ?string $src, ?string $index): ?Image
    {
        $src = trim((string) $src);
        if ($src !== '') {
            return $images->first(fn (Image $image) => self::matches($src, $image->original_name));
        }

        $index = trim((string) $index);
        if ($index !== '' && ctype_digit($index)) {
            return $images->values()->get((int) $index);
        }

        return null;
    }

    /** Подходит ли src="..." к картинке с таким исходным именем файла. */
    public static function matches(string $src, ?string $originalName): bool
    {
        $src = mb_strtolower(trim($src));
        $original = mb_strtolower((string) $originalName);

        return $src !== '' && ($original === $src || self::key($originalName) === $src);
    }

    /**
     * Ключ картинки внутри поста: имя файла без расширения, в нижнем
     * регистре. По нему же определяется, что новая загрузка заменяет
     * существующую картинку.
     */
    public static function key(?string $originalName): string
    {
        return mb_strtolower(pathinfo((string) $originalName, PATHINFO_FILENAME));
    }

    /** Имя без расширения, как его писать в src="..." (регистр сохраняется). */
    public static function srcFor(?string $originalName): string
    {
        return pathinfo((string) $originalName, PATHINFO_FILENAME);
    }
}
