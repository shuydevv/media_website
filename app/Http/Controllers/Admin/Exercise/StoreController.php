<?php

namespace App\Http\Controllers\Admin\Exercise;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Exercise\StoreRequest;
use App\Models\Category;
use App\Models\Exercise;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StoreController extends BaseController
{
    public function __invoke(StoreRequest $request) {
        $data = $request->validated();
        $this->service->store($data);
        

        return redirect()->route('admin.exercises.index');
    }
}
