<?php

namespace App\Http\Controllers\Admin\Shpargalka;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CreateController extends Controller
{
    public function __invoke() {
        $categories = Category::all();
        return view('admin.shpargalkas.create', compact('categories'));
    }
}
