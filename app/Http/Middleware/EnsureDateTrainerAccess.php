<?php

namespace App\Http\Middleware;

use App\Service\Dates\HistAccess;
use Closure;
use Illuminate\Http\Request;

class EnsureDateTrainerAccess
{
    public function __construct(private HistAccess $access)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || !$this->access->allows($request->user())) {
            abort(403, 'Тренажёр дат доступен ученикам курса истории.');
        }

        return $next($request);
    }
}
