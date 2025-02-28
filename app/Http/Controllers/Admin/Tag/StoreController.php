<?php

namespace App\Http\Controllers\Admin\Tag;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Category\StoreRequest;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Http\Request;


class StoreController extends Controller
{
    public function __invoke(StoreRequest $request) {
        $data = $request->validated();
        // Category::firstOrCreate(['title' => $data['title']], [ // Проверка, есть ли тайтл 
        //     'title' => $data['title'],
        // ]);
        Tag::firstOrCreate($data);
        return redirect()->route('admin.tag.index');
        // dd($data);
        // return view('admin.categories.store');
    }
}
