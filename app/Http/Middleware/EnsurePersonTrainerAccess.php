<?php

namespace App\Http\Middleware;

use App\Service\Dates\HistAccess;
use Closure;
use Illuminate\Http\Request;

/** Тренажёр личностей открыт тем же, кому тренажёр дат (App\Service\Dates\HistAccess). */
class EnsurePersonTrainerAccess
{
    public function __construct(private HistAccess $access)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || !$this->access->allows($request->user())) {
            abort(403, 'Тренажёр личностей доступен ученикам курса истории.');
        }

        return $next($request);
    }
}
