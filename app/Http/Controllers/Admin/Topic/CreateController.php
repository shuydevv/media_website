<?php

namespace App\Http\Controllers\Admin\Topic;

use App\Http\Controllers\Controller;
use App\Models\Section;
use Illuminate\Http\Request;

class CreateController extends Controller
{
    public function __invoke() {
        $sections = Section::all();
        return view('admin.topics.create', compact('sections'));
    }
}
