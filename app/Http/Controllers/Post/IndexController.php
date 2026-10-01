<?php

namespace App\Http\Controllers\Post;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;

class IndexController extends Controller
{
    public function __invoke()
    {
        $postCategory = request()->query('post_category');

        $categoryTitle = match ($postCategory) {
            'history' => 'История',
            'social_science' => 'Обществознание',
            default => null,
        };

        // Планы живут отдельно на /plans. Исключаем их в самом запросе — раньше
        // их выкидывал шаблон уже после paginate(), и на странице оказывалось
        // меньше 4 карточек (вплоть до пустой страницы).
        $query = Post::query()->articles()->with(['tags', 'category']);

        if ($categoryTitle !== null) {
            $category = Category::where('title', $categoryTitle)->first();
            // Категория могла быть переименована/удалена — тогда просто
            // ничего не находим, а не падаем на ->id от null (как было).
            $query->where('category_id', $category?->id ?? 0);
        }

        $posts = $query->paginate(4)->withQueryString();

        return view('post.index', compact('posts'));
    }
}
