<?php

namespace App\Http\Controllers\Admin\Exercise;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Http\Request;
// use PostService;
use App\Service\ExerciseService;

class BaseController extends Controller
{
    public $service;

    public function __construct(ExerciseService $service) {
        $this->service = $service;
    }   
}
