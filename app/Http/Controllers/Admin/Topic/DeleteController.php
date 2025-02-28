<?php

namespace App\Http\Controllers\Admin\Topic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Category\UpdateRequest;
use App\Models\Topic;
use Illuminate\Http\Request;

class DeleteController extends Controller
{
    public function __invoke(Topic $topic) {
        // $data = $request->validated();
        $topic->delete();
        return redirect()->route('admin.topic.index');
    }
}
