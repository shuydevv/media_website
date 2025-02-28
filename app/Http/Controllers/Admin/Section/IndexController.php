<?php

namespace App\Http\Controllers\Admin\Section;

use App\Http\Controllers\Controller;
use App\Models\Section;
use Illuminate\Http\Request;

class IndexController extends Controller
{
    public function __invoke() {
        $sections = Section::all();
        return view('admin.sections.index', compact('sections'));
    }
}
