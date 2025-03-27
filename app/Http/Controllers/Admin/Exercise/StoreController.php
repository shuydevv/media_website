<?php

namespace App\Http\Controllers\Admin\Exercise;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Exercise\StoreRequest;
use App\Models\Category;
use App\Models\Exercise;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

// class StoreController extends BaseController
// {
//     public function __invoke(StoreRequest $request) {
//         $data = $request->validated();
//         dd($data)
//         $this->service->store($data);
//         return redirect()->route('admin.exercises.index');
//     }
// }

class StoreController extends Controller
{
    public function __invoke(StoreRequest $request) {
        // dd($request);
        $data = $request->validated();
        // dd($data);

        if( array_key_exists('main_image', $data)) {
            $data['main_image'] = Storage::disk('public')->put('/images', $data['main_image']);
        }

        Exercise::firstOrCreate($data);
        return redirect()->route('admin.exercise.index');

        // dd($data)
        // $this->service->store($data);
        // return redirect()->route('admin.exercises.index');
    }
}
