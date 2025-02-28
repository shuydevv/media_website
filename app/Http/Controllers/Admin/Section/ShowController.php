<?php

namespace App\Http\Controllers\Admin\Section;

use App\Http\Controllers\Controller;
use App\Models\Section;
use Illuminate\Http\Request;

class ShowController extends Controller
{
    public function __invoke(Section $section) {
        $sections = Section::all();
        return view('admin.sections.show', compact('section'));
    }
}
