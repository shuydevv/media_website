<?php

namespace App\Http\Controllers\Admin\Shpargalka;

use App\Http\Controllers\Controller;
use App\Models\Shpargalka;
use Illuminate\Http\Request;

class ShowController extends Controller
{
    public function __invoke(Shpargalka $shpargalka) {
        $shpargalkas = Shpargalka::all();
        return view('admin.shpargalkas.show', compact('shpargalka'));
    }
}
