<?php

namespace App\View\Components\Concerns;

use App\Models\Image;
use App\Models\Post;
use Illuminate\Support\Collection;

/**
 * Общая логика для компонентов, встраиваемых в HTML-контент поста
 * (<x-img>, <x-person>, <x-quote>) — им нужны картинки поста, внутри
 * которого их рендерят. Раньше каждый компонент сам, независимо и
 * одинаково хрупко выяснял "текущий пост": разбирал Request::url()
 * регуляркой на последний сегмент пути и полным сканом искал пост с таким
 * path. Это ломалось на любом отклонении URL от чистого /posts/{path}
 * (конечный слэш, прокси/CDN, предпросмотр) — тогда $post оказывался null,
 * и картинка внутри контента просто не показывалась. При этом контроллер
 * уже находит нужный Post через route model binding — просто нужно его
 * забрать, а не искать заново.
 */
trait ResolvesCurrentPostImages
{
    protected function currentPostImages(): Collection
    {
        $post = request()->route('post');

        if (!($post instanceof Post)) {
            return collect();
        }

        return Image::where('post_id', $post->id)->orderBy('id')->get();
    }
}
