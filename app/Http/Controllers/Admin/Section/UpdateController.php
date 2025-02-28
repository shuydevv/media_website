<?php

namespace App\Http\Controllers\Admin\Section;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Section\UpdateRequest;
use App\Models\Section;
use Illuminate\Http\Request;

class UpdateController extends Controller
{
    public function __invoke(UpdateRequest $request, Section $section) {
        $data = $request->validated();
        $section->update($data);
        return view('admin.sections.show', compact('section'));
    }
}
