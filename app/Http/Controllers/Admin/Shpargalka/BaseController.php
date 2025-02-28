<?php

namespace App\Http\Controllers\Admin\Shpargalka;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Http\Request;
// use PostService;
use App\Service\ShpargalkaService;

class BaseController extends Controller
{
    public $service;

    public function __construct(ShpargalkaService $service) {
        $this->service = $service;
    }   
}
