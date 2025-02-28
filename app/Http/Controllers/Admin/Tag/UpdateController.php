<?php

namespace App\Http\Controllers\Admin\Tag;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Category\UpdateRequest;
use App\Models\Category;
use App\Models\Tag;


use Illuminate\Http\Request;

class UpdateController extends Controller
{
    public function __invoke(UpdateRequest $request, Tag $tag) {
        $data = $request->validated();
        $tag->update($data);
        return view('admin.tags.show', compact('tag'));
    }
}
