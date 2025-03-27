<?php

namespace App\Http\Controllers\Admin\Exercise;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Exercise;
use App\Models\Tag;


use Illuminate\Http\Request;

class ShowController extends BaseController
{
    public function __invoke(Exercise $exercise) {
        $exercises = Exercise::all();
        return view('admin.exercises.show', compact( 'exercise'));
    }
}
