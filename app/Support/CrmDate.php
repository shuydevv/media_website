<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * "02 ноя 2026" — короткий формат дат в /admin/crm. Не Carbon::translatedFormat(),
 * потому что стандартные русские сокращения месяцев из локали Carbon длиннее и с
 * точкой ("нояб.", "февр."), а здесь нужны ровно 3 буквы без точки — общий
 * помощник, чтобы формат не разъезжался между Blade-вьюхой и JSON-ответом
 * AccessController (там же дата подставляется в тот же текст после сохранения).
 */
class CrmDate
{
    private const MONTHS = ['янв', 'фев', 'мар', 'апр', 'май', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];

    public static function format(?Carbon $date): ?string
    {
        if (!$date) {
            return null;
        }

        return sprintf('%02d %s %d', $date->day, self::MONTHS[$date->month - 1], $date->year);
    }
}
