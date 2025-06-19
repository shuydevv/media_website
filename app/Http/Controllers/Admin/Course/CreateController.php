<?php


namespace App\Http\Controllers\Admin\Course;

use App\Http\Controllers\Controller;
use App\Models\Category;

class CreateController extends Controller
{
    public function __invoke()
    {
        $categories = Category::all();
        return view('admin.courses.create', compact('categories'));
    }
}