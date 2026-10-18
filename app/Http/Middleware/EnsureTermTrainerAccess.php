<?php

namespace App\Http\Middleware;

use App\Service\Terms\TermAccess;
use Closure;
use Illuminate\Http\Request;

class EnsureTermTrainerAccess
{
    public function __construct(private TermAccess $access)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || !$this->access->allows($request->user())) {
            abort(403, 'Тренажёр терминов доступен ученикам курса обществознания.');
        }

        return $next($request);
    }
}
