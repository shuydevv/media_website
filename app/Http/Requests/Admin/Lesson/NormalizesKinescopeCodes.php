<?php

namespace App\Http\Requests\Admin\Lesson;

/**
 * Поля видео урока хранят только код Kinescope (то, что после
 * https://kinescope.io/). Если вставили ссылку целиком — с kinescope.io или
 * kinescope.ru, с /embed/ или без, с параметрами — берём из неё код, иначе
 * плеер и чат получили бы неверный адрес.
 */
trait NormalizesKinescopeCodes
{
    protected function prepareForValidation(): void
    {
        $fix = [];
        foreach (['meet_link', 'recording_link', 'short_class'] as $field) {
            if (is_string($v = $this->input($field))) {
                $fix[$field] = self::kinescopeCode($v);
            }
        }
        $this->merge($fix);
    }

    public static function kinescopeCode(string $value): string
    {
        $value = trim($value);
        if (! str_contains($value, '/')) {
            return $value;
        }
        $path = parse_url(str_contains($value, '://') ? $value : 'https://'.$value, PHP_URL_PATH) ?: '';
        $parts = array_values(array_filter(explode('/', $path), fn ($p) => $p !== '' && ! in_array($p, ['embed', 'video', 'live', 'chat'], true)));

        return $parts ? end($parts) : $value;
    }
}
