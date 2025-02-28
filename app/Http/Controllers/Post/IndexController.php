<?php

namespace App\Http\Controllers\Post;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use Illuminate\Http\Request;

class IndexController extends Controller
{
    public function __invoke() {
        // $posts = [];
        if (request()->query('post_category') == 'history') {
            $category = Category::all()->where('title', 'История')->first();
            $items = Post::where('category_id', $category->id);
            $posts = $items->paginate(4)->withQueryString();;
        } else if (request()->query('post_category') == 'social_science') {
            $category = Category::all()->where('title', 'Обществознание')->first();
            $items = Post::where('category_id', $category->id);
            $posts = $items->paginate(4)->withQueryString();;
        } 
        // dd($posts);
        else {
            $posts = Post::paginate(4)->withQueryString();;
            // dd($posts);
        }

        // dd(request()->query('post_category'));
        return view('post.index', compact('posts'));
    }
}
