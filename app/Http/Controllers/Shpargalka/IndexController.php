<?php

namespace App\Http\Controllers\Shpargalka;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Models\Shpargalka;
use Illuminate\Http\Request;

class IndexController extends Controller
{
    public function __invoke() {

        if (request()->query('material_category') == 'history') {
            $category = Category::all()->where('title', 'История')->first();
            $items = Shpargalka::where('category_id', $category->id);
            $posts = $items->paginate(4)->withQueryString();;
        } else if (request()->query('material_category') == 'social_science') {
            $category = Category::all()->where('title', 'Обществознание')->first();
            $items = Shpargalka::where('category_id', $category->id);
            $posts = $items->paginate(4)->withQueryString();;
        } 

        else {
            $posts = Shpargalka::paginate(4)->withQueryString();;

        }
        $categories = Category::all();

        // dd(request()->query('post_category'));
        return view('shpargalka.index', compact('posts', 'categories'));
    }
}
