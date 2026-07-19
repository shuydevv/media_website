<?php

namespace App\Http\Controllers\Post;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Image;
use App\Models\Post;

class ShowController extends Controller
{
    public function __invoke(Post $post)
    {
        // Раньше "текущий пост" для похожих статей заново находился регуляркой
        // по Request::url() вместо использования $post, который Laravel уже
        // резолвил через route model binding — ломалось на любом отклонении
        // URL (конечный слэш и т.п.) и требовало лишнего запроса в БД.
        $images = Image::where('post_id', $post->id)->orderBy('id')->get();

        // "Планы по обществознанию" — служебная категория, не показываем
        // такие статьи в блоке "Другие статьи".
        $excludedCategoryId = Category::where('title', 'Планы по обществознанию')->value('id');

        $posts = Post::query()
            ->with(['tags', 'category'])
            ->where('category_id', $post->category_id)
            ->where('path', '!=', $post->path)
            ->when($excludedCategoryId !== null, fn ($q) => $q->where('category_id', '!=', $excludedCategoryId))
            ->paginate(4);

        return view('post.show', compact('post', 'posts', 'images'));
    }
}
