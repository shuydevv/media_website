<?php

namespace App\Http\Controllers\Admin\Post;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Image;
use App\Models\Post;
use App\Models\Section;
use App\Models\Tag;
use Illuminate\Http\Request;

class EditController extends BaseController
{
    public function __invoke(Post $post) {
        $categories = Category::all();
        $tags = Tag::all();
        $sections = Section::with('topics')->orderBy('id')->get();
        // Порядок по id — тот же, что у старых img="N" на странице поста.
        $images = Image::where('post_id', $post->id)->orderBy('id')->get();
        return view('admin.posts.edit', compact('post', 'tags', 'categories', 'images', 'sections'));
    }
}
