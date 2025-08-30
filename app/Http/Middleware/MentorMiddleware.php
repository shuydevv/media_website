<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MentorMiddleware
{
    // Чётко фиксируем разрешённые роли тут, чтобы не тянуть методы из модели User
    private const ROLE_ADMIN  = 1;
    private const ROLE_MENTOR = 3;

    /**
     * Доступ только для админа (1) и куратора (3).
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            abort(403, 'Неавторизован.');
        }

        $role = (int) (auth()->user()->role ?? 0);

        if (!in_array($role, [self::ROLE_ADMIN, self::ROLE_MENTOR], true)) {
            abort(403, 'Доступ разрешён только кураторам и администраторам.');
        }

        return $next($request);
    }
}
