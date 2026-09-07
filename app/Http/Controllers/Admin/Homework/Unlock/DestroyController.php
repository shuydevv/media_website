<?php

namespace App\Http\Controllers\Admin\Homework\Unlock;

use App\Http\Controllers\Controller;
use App\Models\Homework;
use App\Models\User;

class DestroyController extends Controller
{
    public function __invoke(Homework $homework, User $user)
    {
        $homework->unlocks()->where('user_id', $user->id)->delete();

        return back()->with('success', 'Доступ к домашке отозван.');
    }
}
