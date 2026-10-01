<?php

namespace App\Http\Controllers\Admin\Post;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Section;
use App\Models\Tag;
use Illuminate\Http\Request;

class CreateController extends BaseController
{
    public function __invoke() {
        $categories = Category::all();
        $tags = Tag::all();
        $sections = Section::with('topics')->orderBy('id')->get();
        return view('admin.posts.create', compact('categories', 'tags', 'sections'));
    }
}
