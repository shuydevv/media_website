<?php

namespace App\Http\Controllers\Admin\Section;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CreateController extends Controller
{
    public function __invoke() {
        $categories = Category::all();
        return view('admin.sections.create', compact('categories'));
    }
}
