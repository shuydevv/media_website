<?php

namespace App\Http\Controllers\Exercise;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Exercise;
use App\Models\Section;

class ShowController extends Controller
{
    public function __invoke(Exercise $exercise) {
        $categories = Category::all();
        $sections = Section::all();

        return view('exercise.show', compact('exercise', 'categories', 'sections'));
    }
}
