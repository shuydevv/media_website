<?php

namespace App\Http\Controllers\Admin\Homework;

use App\Http\Controllers\Controller;
use App\Models\Homework;

class IndexController extends Controller
{
    public function __invoke()
    {
        $homeworks = Homework::latest()->paginate(20);
        return view('admin.homeworks.index', compact('homeworks'));
    }
}
