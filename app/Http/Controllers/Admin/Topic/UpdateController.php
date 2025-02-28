<?php

namespace App\Http\Controllers\Admin\Topic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Topic\UpdateRequest;
use App\Models\Topic;
use Illuminate\Http\Request;

class UpdateController extends Controller
{
    public function __invoke(UpdateRequest $request, Topic $topic) {
        $data = $request->validated();
        $topic->update($data);
        return view('admin.topics.show', compact('topic'));
    }
}
