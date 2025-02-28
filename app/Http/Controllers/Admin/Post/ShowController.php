<?php

namespace App\Http\Controllers\Admin\Post;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;


use Illuminate\Http\Request;

class ShowController extends BaseController
{
    public function __invoke(Post $post) {
        $posts = Post::all();
        return view('admin.posts.show', compact('post'));
    }
}
