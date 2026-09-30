<?php

namespace App\Support;

class BladeContent
{
    /**
     * Чинит «прямые» двойные кавычки внутри значений атрибутов компонентов
     * (<x-text text="...героем "Слова о полку Игореве"..." />) перед
     * Blade::render(). Контент постов вставляется из админки как есть, и
     * кавычки внутри текста там обычное дело. Без этого Blade считает, что
     * значение атрибута закончилось на первой внутренней кавычке, тег не
     * матчится как компонент и уходит в браузер сырым <x-text ...> — текст
     * пропадает, а соседние компоненты (<x-img> и т.п.) ломаются вместе с ним.
     *
     * Настоящая закрывающая кавычка — та, за которой идёт следующий атрибут
     * (name=") или конец тега (/> или >). Все остальные " внутри значения
     * считаются текстовыми и заменяются на «ёлочки» попеременно.
     */
    public static function fixAttributeQuotes(?string $content): string
    {
        if ($content === null || $content === '') {
            return '';
        }

        $s = $content;
        $out = '';
        $offset = 0;

        while (preg_match('/<x-[\w.\-:]+/u', $s, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $tagStart = $m[0][1];
            $pos = $tagStart + strlen($m[0][0]);
            $out .= substr($s, $offset, $pos - $offset);

            // Идём по атрибутам тега до его конца.
            while ($pos < strlen($s)) {
                if (preg_match('/\G\s*(\/?>)/u', $s, $end, 0, $pos)) {
                    $out .= $end[0];
                    $pos += strlen($end[0]);
                    break;
                }

                if (!preg_match('/\G(\s*[:@]?[\w.\-:]+=")/u', $s, $attr, 0, $pos)) {
                    // Не атрибут вида name="..." (булев атрибут, {{ }} и т.п.) —
                    // оставляем остаток тега как есть.
                    break;
                }

                $out .= $attr[0];
                $valueStart = $pos + strlen($attr[0]);
                $valueEnd = self::findClosingQuote($s, $valueStart);

                if ($valueEnd === null) {
                    $pos = $valueStart;
                    break;
                }

                $out .= self::typographQuotes(substr($s, $valueStart, $valueEnd - $valueStart)) . '"';
                $pos = $valueEnd + 1;
            }

            $offset = $pos;
        }

        return $out . substr($s, $offset);
    }

    private static function findClosingQuote(string $s, int $from): ?int
    {
        $len = strlen($s);
        for ($i = $from; $i < $len; $i++) {
            if ($s[$i] !== '"') {
                continue;
            }
            if (preg_match('/\G"(\s*\/?>|\s+[:@]?[\w.\-:]+=")/u', $s, $_, 0, $i)) {
                return $i;
            }
        }

        return null;
    }

    private static function typographQuotes(string $value): string
    {
        $open = true;

        return preg_replace_callback('/"/', function () use (&$open) {
            $q = $open ? '«' : '»';
            $open = !$open;

            return $q;
        }, $value);
    }
}
