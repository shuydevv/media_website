<?php

namespace App\Http\Controllers\Admin\Section;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Section;
use Illuminate\Http\Request;

class EditController extends Controller
{
    public function __invoke(Section $section) {
        $categories = Category::all();
        return view('admin.sections.edit', compact('section', 'categories'));
    }
}
