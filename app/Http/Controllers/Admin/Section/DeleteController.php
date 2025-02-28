<?php

namespace App\Http\Controllers\Admin\Section;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Category\UpdateRequest;
use App\Models\Section;
use Illuminate\Http\Request;

class DeleteController extends Controller
{
    public function __invoke(Section $section) {
        // $data = $request->validated();
        $section->delete();
        return redirect()->route('admin.section.index');
    }
}
