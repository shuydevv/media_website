<?php

namespace App\Http\Controllers\Admin\Exercise;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Section;
use App\Models\Tag;
use App\Models\Topic;
use Illuminate\Http\Request;

class CreateController extends BaseController
{
    public function __invoke() {
        $categories = Category::all();
        $topics = Topic::all();
        $sections = Section::all();
        // $tags = Tag::all();
        return view('admin.exercises.create', compact('categories', 'sections', 'topics'));
    }
}
