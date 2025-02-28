<?php

namespace App\Http\Controllers\Admin\Shpargalka;

use App\Http\Controllers\Controller;
use App\Models\Shpargalka;
use Illuminate\Http\Request;

class IndexController extends Controller
{
    public function __invoke() {
        $shpargalkas = Shpargalka::all();
        return view('admin.shpargalkas.index', compact('shpargalkas'));
    }
}
