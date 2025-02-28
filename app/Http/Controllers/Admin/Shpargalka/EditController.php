<?php

namespace App\Http\Controllers\Admin\Shpargalka;

use App\Http\Controllers\Controller;
use App\Models\Shpargalka;
use App\Models\Category;
use App\Models\Image;
use App\Models\Tag;
use Illuminate\Http\Request;

class EditController extends Controller
{
    public function __invoke(Shpargalka $shpargalka) {
        $categories = Category::all();
        $tags = Tag::all();
        $images = Image::all();
        return view('admin.shpargalkas.edit', compact('shpargalka', 'tags', 'categories', 'images'));
    }
}
