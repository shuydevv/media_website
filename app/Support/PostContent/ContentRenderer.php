<?php

namespace App\Support\PostContent;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

/**
 * Рендер контента поста (<x-h2 />, <x-text />, <x-img /> ...) без
 * Blade::render().
 *
 * Раньше контент компилировался как живой Blade-шаблон: любой, кто может
 * править пост, мог выполнить PHP на сервере ({{ }}, @php), а прямая кавычка
 * внутри text="..." ломала тег целиком — он уходил в браузер сырым, и
 * пропадал текст вместе с соседними картинками. Здесь теги разбираются сами,
 * допускаются только компоненты из белого списка, а значения атрибутов
 * попадают в те же шаблоны components/* как обычные строки (экранируются
 * через {{ }}). Обычный HTML между тегами выводится как есть — как и раньше.
 *
 * Картинки передаются явно (картинки этого поста), а не ищутся компонентом
 * по текущему запросу.
 */
class ContentRenderer
{
    /** Разрешённые компоненты → атрибуты, которые ждёт их шаблон. */
    private const COMPONENTS = [
        'h2' => ['title'],
        'h3' => ['title'],
        'text' => ['text'],
        'ul' => ['text'],
        'li' => ['text'],
        'quote_text' => ['text', 'source'],
        'quote' => ['text', 'name', 'description'],
        'date' => ['date', 'fact'],
        'img' => ['description'],
        'person' => ['title', 'description'],
    ];

    private const IMAGE_COMPONENTS = ['img', 'person', 'quote'];

    public function render(?string $content, Collection $images, array $logContext = []): HtmlString
    {
        $content = (string) $content;
        $pos = 0;
        $nodes = $this->parseNodes($content, $pos, null, $logContext);

        return new HtmlString($this->renderNodes($nodes, $images, $logContext));
    }

    /**
     * Узел — либо строка (HTML как есть), либо компонент
     * ['name' => ..., 'attrs' => [...], 'children' => [...]].
     */
    private function parseNodes(string $s, int &$pos, ?string $until, array $logContext): array
    {
        $nodes = [];
        $len = strlen($s);

        while ($pos < $len) {
            if (!preg_match('/<(\/?)x-([\w.\-:]+)/u', $s, $m, PREG_OFFSET_CAPTURE, $pos)) {
                $nodes[] = substr($s, $pos);
                $pos = $len;
                break;
            }

            $start = $m[0][1];
            if ($start > $pos) {
                $nodes[] = substr($s, $pos, $start - $pos);
            }

            if ($m[1][0] === '/') {
                $pos = preg_match('/\G<\/x-[\w.\-:]+\s*>/u', $s, $close, 0, $start)
                    ? $start + strlen($close[0])
                    : $start + strlen($m[0][0]);

                if ($m[2][0] === $until) {
                    return $nodes;
                }
                // Лишний закрывающий тег — просто выбрасываем.
                continue;
            }

            $tag = ComponentTag::parseOpening($s, $start);
            if ($tag === null) {
                Log::warning('Post content: не удалось разобрать тег', $logContext + [
                    'fragment' => mb_substr(substr($s, $start), 0, 120),
                ]);
                $gt = strpos($s, '>', $start);
                $pos = $gt === false ? $len : $gt + 1;
                continue;
            }

            $pos = $tag['end'];
            $nodes[] = [
                'name' => $tag['name'],
                'attrs' => $this->attributeValues($tag['attrs']),
                'children' => $tag['selfClosing'] ? [] : $this->parseNodes($s, $pos, $tag['name'], $logContext),
            ];
        }

        return $nodes;
    }

    private function attributeValues(array $attrs): array
    {
        $values = [];
        foreach ($attrs as $attr) {
            $value = html_entity_decode((string) $attr['value'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $values[$attr['name']] = $attr['quote'] === '"' ? ComponentTag::typographQuotes($value) : $value;
        }

        return $values;
    }

    private function renderNodes(array $nodes, Collection $images, array $logContext): string
    {
        $html = '';
        foreach ($nodes as $node) {
            $html .= is_string($node) ? $node : $this->renderComponent($node, $images, $logContext);
        }

        return $html;
    }

    private function renderComponent(array $node, Collection $images, array $logContext): string
    {
        $name = $node['name'];

        if (!isset(self::COMPONENTS[$name])) {
            Log::warning('Post content: неизвестный компонент', $logContext + ['tag' => $name]);

            return '';
        }

        $data = [];
        foreach (self::COMPONENTS[$name] as $attr) {
            $data[$attr] = $node['attrs'][$attr] ?? '';
        }

        if (in_array($name, self::IMAGE_COMPONENTS, true)) {
            $src = $node['attrs']['src'] ?? null;
            $index = $node['attrs']['img'] ?? null;
            $data['image'] = PostImages::find($images, $src, $index);

            if ($data['image'] === null && ($src ?? $index ?? '') !== '') {
                Log::warning('Post content: картинка не найдена среди картинок поста', $logContext + [
                    'tag' => $name,
                    'src' => $src,
                    'img' => $index,
                    'available' => $images->pluck('original_name')->all(),
                ]);
            }
        }

        $data['slot'] = new HtmlString($this->renderNodes($node['children'], $images, $logContext));

        return view('components.' . $name, $data)->render();
    }
}
