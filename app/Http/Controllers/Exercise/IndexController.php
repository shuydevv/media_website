<?php

namespace App\Http\Controllers\Exercise;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Exercise;
use App\Models\Post;
use Illuminate\Http\Request;

class IndexController extends Controller
{
    private const CATEGORY_TITLES_BY_SLUG = [
        'history' => 'История',
        'social_science' => 'Обществознание',
    ];

    public function __invoke() {
        $slug = request()->query('post_category');
        $categoryTitle = self::CATEGORY_TITLES_BY_SLUG[$slug] ?? null;

        if ($categoryTitle !== null) {
            // exercises не имеют собственной колонки category_id — категория
            // определяется через цепочку topic -> section -> category
            // (см. Exercise::getCategoryAttribute()), поэтому фильтруем через
            // whereHas по этой цепочке, а не прямым where по несуществующей колонке.
            $category = Category::where('title', $categoryTitle)->first();
            $posts = $category
                ? Exercise::whereHas('topic.section', fn ($q) => $q->where('category_id', $category->id))
                    ->paginate(4)->withQueryString()
                : Exercise::whereRaw('1 = 0')->paginate(4)->withQueryString();
        } else {
            $posts = Exercise::paginate(4)->withQueryString();
        }

        return view('exercise.index', compact('posts'));
    }
}
