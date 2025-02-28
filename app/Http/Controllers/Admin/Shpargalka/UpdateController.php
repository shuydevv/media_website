<?php

namespace App\Http\Controllers\Admin\Shpargalka;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Shpargalka\UpdateRequest;
use App\Models\Shpargalka;
use Illuminate\Http\Request;

class UpdateController extends BaseController
{
    public function __invoke(UpdateRequest $request, Shpargalka $shpargalka) {
        $data = $request->validated();
        // dd($data);
        $shpargalka = $this->service->update($data, $shpargalka);
        return view('admin.shpargalkas.show', compact('shpargalka'));
    }
}
