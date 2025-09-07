<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomeRedirectController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        // Если используешь spatie/permission:
        if (method_exists($user, 'hasRole')) {
            if ($user->hasRole('Admin')) {
                return redirect()->to('/admin');
            }
            if ($user->hasRole('Mentor')) {
                return redirect()->to('/mentor/submissions/');
            }
            // По умолчанию — студент
            return redirect()->to('/student/dashboard');
        }

        // Если роль — простая колонка users.role
        switch ($user->role) {
            case 'Admin':
                return redirect()->to('/admin');
            case 'Mentor':
                return redirect()->to('/mentor/submissions/');
            case 'Student':
            case 'User':
            default:
                return redirect()->to('/student/dashboard');
        }
    }
}
