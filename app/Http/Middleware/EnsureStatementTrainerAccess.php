<?php

namespace App\Http\Middleware;

use App\Service\Terms\TermAccess;
use Closure;
use Illuminate\Http\Request;

/**
 * «Верные суждения» стоят в зале обществоведов — доступ тот же, что у
 * тренажёра терминов (TermAccess): курс обществознания, привязанный курс
 * или личная выдача в /admin/terms/access.
 */
class EnsureStatementTrainerAccess
{
    public function __construct(private TermAccess $access)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        if (! $request->user() || ! $this->access->allows($request->user())) {
            abort(403, 'Верные суждения доступны ученикам курса обществознания.');
        }

        return $next($request);
    }
}
