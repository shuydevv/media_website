<?php

namespace App\Support\PostContent;

/**
 * Разбор открывающего тега компонента в контенте (<x-text text="..." />).
 *
 * Контент вставляется из админки руками, и прямые кавычки внутри текста там
 * обычное дело (text="...героем "Слова о полку Игореве"..."). Поэтому
 * закрывающей кавычкой значения считается только та, за которой идёт
 * следующий атрибут (name=") или конец тега (/> или >), а не первая
 * попавшаяся.
 */
final class ComponentTag
{
    /**
     * @return array{
     *     name: string,
     *     attrs: list<array{name: string, value: ?string, quote: string}>,
     *     end: int,
     *     selfClosing: bool,
     * }|null  null — тег не удалось разобрать
     */
    public static function parseOpening(string $s, int $offset): ?array
    {
        if (!preg_match('/\G<x-([\w.\-:]+)/u', $s, $m, 0, $offset)) {
            return null;
        }

        $name = $m[1];
        $pos = $offset + strlen($m[0]);
        $len = strlen($s);
        $attrs = [];

        while ($pos < $len) {
            if (preg_match('/\G\s*(\/?)>/u', $s, $end, 0, $pos)) {
                return [
                    'name' => $name,
                    'attrs' => $attrs,
                    'end' => $pos + strlen($end[0]),
                    'selfClosing' => $end[1] === '/',
                ];
            }

            if (preg_match('/\G\s+([:@]?[\w.\-:]+)=(["\'])/u', $s, $a, 0, $pos)) {
                $valueStart = $pos + strlen($a[0]);
                $valueEnd = self::findClosingQuote($s, $valueStart, $a[2]);
                if ($valueEnd === null) {
                    return null;
                }
                $attrs[] = [
                    'name' => $a[1],
                    'value' => substr($s, $valueStart, $valueEnd - $valueStart),
                    'quote' => $a[2],
                ];
                $pos = $valueEnd + 1;
                continue;
            }

            // Булев атрибут без значения.
            if (preg_match('/\G\s+([\w.\-:]+)(?=\s|\/?>)/u', $s, $a, 0, $pos)) {
                $attrs[] = ['name' => $a[1], 'value' => null, 'quote' => ''];
                $pos += strlen($a[0]);
                continue;
            }

            return null;
        }

        return null;
    }

    /** Прямые двойные кавычки внутри текста → «ёлочки» попеременно. */
    public static function typographQuotes(string $value): string
    {
        $open = true;

        return preg_replace_callback('/"/', function () use (&$open) {
            $q = $open ? '«' : '»';
            $open = !$open;

            return $q;
        }, $value);
    }

    private static function findClosingQuote(string $s, int $from, string $quote): ?int
    {
        $len = strlen($s);
        for ($i = $from; $i < $len; $i++) {
            if ($s[$i] !== $quote) {
                continue;
            }
            if (preg_match('/\G' . $quote . '(\s*\/?>|\s+[:@]?[\w.\-:]+=["\'])/u', $s, $_, 0, $i)) {
                return $i;
            }
        }

        return null;
    }
}
