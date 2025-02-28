<?php

namespace App\Http\Controllers\Admin\Section;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Section\StoreRequest;
use App\Models\Section;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function __invoke(StoreRequest $request) {
        
        // $data = $request;
        $data = $request->validated();
        // dd($data);
        // Category::firstOrCreate(['title' => $data['title']], [ // Проверка, есть ли тайтл 
        //     'title' => $data['title'],
        // ]);
        Section::firstOrCreate($data);
        return redirect()->route('admin.section.index');
        // dd($data);
        // return view('admin.categories.store');
    }
}
