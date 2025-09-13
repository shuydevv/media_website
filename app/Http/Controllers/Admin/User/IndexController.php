<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class IndexController extends Controller
{
    public function __invoke(Request $request) {
    $q = trim($request->string('q')->toString());

    $users = User::query()
        // если нужны и удалённые, добавь ->withTrashed()
        ->when($q, function ($query) use ($q) {
            $query->where(function ($s) use ($q) {
                $s->where('name', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%")
                  ->orWhere('phone', 'like', "%{$q}%")
                  ->orWhere('first_name', 'like', "%{$q}%")
                  ->orWhere('last_name', 'like', "%{$q}%");
            });
        })
        ->orderByDesc('created_at')
        ->paginate(20)
        ->withQueryString();

    return view('admin.users.index', compact('users', 'q'));
    }
}
