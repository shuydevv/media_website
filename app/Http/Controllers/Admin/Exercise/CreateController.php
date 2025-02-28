<?php

namespace App\Http\Controllers\Admin\Exercise;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Http\Request;

class CreateController extends BaseController
{
    public function __invoke() {
        $categories = Category::all();
        // $tags = Tag::all();
        return view('admin.exercises.create', compact('categories'));
    }
}
