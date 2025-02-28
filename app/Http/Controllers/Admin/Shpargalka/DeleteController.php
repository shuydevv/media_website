<?php

namespace App\Http\Controllers\Admin\Shpargalka;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Shpargalka\UpdateRequest;
use App\Models\Shpargalka;
use Illuminate\Http\Request;

class DeleteController extends Controller
{
    public function __invoke(Shpargalka $shpargalka) {

        $shpargalka->delete();
        return redirect()->route('admin.shpargalka.index');
    }
}
