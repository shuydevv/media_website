<?php

namespace App\Http\Controllers\Admin\Topic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Topic\StoreRequest;
use App\Models\Topic;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function __invoke(StoreRequest $request) {
        
        $data = $request->validated();
        // dd($data);
        // Category::firstOrCreate(['title' => $data['title']], [ // Проверка, есть ли тайтл 
        //     'title' => $data['title'],
        // ]);
        Topic::firstOrCreate($data);
        return redirect()->route('admin.topic.index');
        // dd($data);
        // return view('admin.categories.store');
    }
}
