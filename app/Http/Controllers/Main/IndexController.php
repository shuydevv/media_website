<?php

namespace App\Http\Controllers\Main;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\Post;
use Illuminate\Http\Request;

class IndexController extends Controller
{
    public function __invoke() {
        return redirect()->route('home');
        // $posts = Post::paginate(4);
        // $images = Image::all();
        // return view('main.index', compact('posts', 'images'));
    }
}
