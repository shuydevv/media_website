<?php

namespace App\Http\Controllers\Admin\Exercise;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Exercise\UpdateRequest;
use App\Models\Category;
use App\Models\Image;
use App\Models\Exercise;
use App\Models\Tag;
use Illuminate\Support\Facades\Storage;



use Illuminate\Http\Request;

class UpdateController extends BaseController
{
    public function __invoke(UpdateRequest $request, Exercise $exercise) {
        $data = $request->validated();
        if( array_key_exists('main_image', $data)) {
            $data['main_image'] = Storage::disk('public')->put('/images', $data['main_image']);
        }
        $exercise->update($data);
        // $exercise = $this->service->update($data, $exercise);

        return view('admin.exercises.show', compact('exercise'));
    }
}
