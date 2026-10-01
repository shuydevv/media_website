<?php

namespace App\Http\Controllers\Plan;

use App\Http\Controllers\Controller;
use App\Service\PlanCatalog;

class IndexController extends Controller
{
    public function __invoke(PlanCatalog $catalog)
    {
        return view('plan.index', ['groups' => $catalog->grouped()]);
    }
}
