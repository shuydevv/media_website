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
            $key = mb_strtolower($src);

            return $images->first(function (Image $image) use ($key) {
                $original = mb_strtolower((string) $image->original_name);

                return $original === $key || pathinfo($original, PATHINFO_FILENAME) === $key;
            });
        }

        $index = trim((string) $index);
        if ($index !== '' && ctype_digit($index)) {
            return $images->values()->get((int) $index);
        }

        return null;
    }
}
