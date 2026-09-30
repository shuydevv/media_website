<?php

namespace App\Support;

use App\Support\PostContent\ComponentTag;

class BladeContent
{
    /**
     * Чинит «прямые» двойные кавычки внутри значений атрибутов компонентов
     * (<x-text text="...героем "Слова о полку Игореве"..." />) перед
     * Blade::render(). Без этого Blade считает, что значение атрибута
     * закончилось на первой внутренней кавычке, тег не матчится как
     * компонент и уходит в браузер сырым <x-text ...> — текст пропадает, а
     * соседние компоненты (<x-img> и т.п.) ломаются вместе с ним.
     *
     * Нужен только там, где контент ещё рендерится через Blade::render()
     * (курсы, шпаргалки). Посты рендерятся через PostContent\ContentRenderer.
     */
    public static function fixAttributeQuotes(?string $content): string
    {
        if ($content === null || $content === '') {
            return '';
        }

        $out = '';
        $offset = 0;

        while (preg_match('/<x-[\w.\-:]+/u', $content, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $start = $m[0][1];
            $out .= substr($content, $offset, $start - $offset);

            $tag = ComponentTag::parseOpening($content, $start);
            if ($tag === null) {
                // Не разобрали — оставляем как есть, пусть Blade решает сам.
                $out .= $m[0][0];
                $offset = $start + strlen($m[0][0]);
                continue;
            }

            $out .= '<x-' . $tag['name'];
            foreach ($tag['attrs'] as $attr) {
                if ($attr['value'] === null) {
                    $out .= ' ' . $attr['name'];
                } elseif ($attr['quote'] === '"' && $attr['name'][0] !== ':') {
                    $out .= ' ' . $attr['name'] . '="' . ComponentTag::typographQuotes($attr['value']) . '"';
                } else {
                    $out .= ' ' . $attr['name'] . '=' . $attr['quote'] . $attr['value'] . $attr['quote'];
                }
            }
            $out .= $tag['selfClosing'] ? ' />' : '>';
            $offset = $tag['end'];
        }

        return $out . substr($content, $offset);
    }
}
