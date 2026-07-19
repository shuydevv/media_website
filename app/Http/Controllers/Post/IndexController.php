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

        $query = Post::query()->with(['tags', 'category']);

        if ($categoryTitle !== null) {
            $category = Category::where('title', $categoryTitle)->first();
            // Категория могла быть переименована/удалена — тогда просто
            // ничего не находим, а не падаем на ->id от null (как было).
            $query->where('category_id', $category?->id ?? 0);
        } else {
            // "Планы по обществознанию" — служебная категория, которую не
            // показываем в общей ленте статей.
            $excludedCategoryId = Category::where('title', 'Планы по обществознанию')->value('id');
            if ($excludedCategoryId !== null) {
                $query->where('category_id', '!=', $excludedCategoryId);
            }
        }

        $posts = $query->paginate(4)->withQueryString();

        return view('post.index', compact('posts'));
    }
}
