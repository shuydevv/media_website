<?php

namespace App\Http\Controllers\Admin\Topic;

use App\Http\Controllers\Controller;
use App\Models\Topic;
use Illuminate\Http\Request;

class ShowController extends Controller
{
    public function __invoke(Topic $topic) {
        $topics = Topic::all();
        return view('admin.topics.show', compact('topic'));
    }
}
