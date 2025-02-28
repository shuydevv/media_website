<?php

namespace App\Http\Controllers\Admin\Topic;

use App\Http\Controllers\Controller;
use App\Models\Topic;
use Illuminate\Http\Request;

class IndexController extends Controller
{
    public function __invoke() {
        $topics = Topic::all();
        return view('admin.topics.index', compact('topics'));
    }
}
