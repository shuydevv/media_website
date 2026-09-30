<?php

namespace App\View\Components\Concerns;

use Illuminate\Support\Collection;

/**
 * Картинки поста, переданные контроллером явно, для компонентов внутри
 * контента (см. ResolvesCurrentPostImages). Живут в контейнере, то есть
 * в рамках одного запроса.
 */
class CurrentPostImages
{
    private const KEY = 'current_post_images';

    public static function provide(iterable $images): void
    {
        app()->instance(self::KEY, collect($images)->values());
    }

    public static function get(): ?Collection
    {
        return app()->bound(self::KEY) ? app(self::KEY) : null;
    }
}
