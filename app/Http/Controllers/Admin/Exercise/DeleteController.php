<?php

namespace App\Http\Controllers\Admin\Exercise;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Category\UpdateRequest;
use App\Models\Exercise;


use Illuminate\Http\Request;

class DeleteController extends BaseController
{
    public function __invoke(Exercise $exercise) {
        // $data = $request->validated();
        $exercise->delete();
        return redirect()->route('admin.exercise.index');
    }
}
