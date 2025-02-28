<?php

namespace App\Http\Controllers\Admin\Exercise;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Image;
use App\Models\Exercise;
use App\Models\Tag;
use Illuminate\Http\Request;

class EditController extends BaseController
{
    public function __invoke(Exercise $exercise) {
        // $categories = Category::all();
        // $tags = Tag::all();
        // $images = Image::all();
        return view('admin.exercises.edit', compact('exercise'));
    }
}
