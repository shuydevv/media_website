<?php

namespace App\Http\Controllers\Admin\Topic;

use App\Http\Controllers\Controller;
use App\Models\Section;
use App\Models\Topic;
use Illuminate\Http\Request;

class EditController extends Controller
{
    public function __invoke(Topic $topic) {
        $sections = Section::all();
        return view('admin.topics.edit', compact('topic', 'sections'));
    }
}
