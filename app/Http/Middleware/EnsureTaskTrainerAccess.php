<?php

namespace App\Http\Middleware;

use App\Service\Terms\TermAccess;
use Closure;
use Illuminate\Http\Request;

/**
 * Станция «Задания» (зал обществоведов) — всем, у кого есть активный курс
 * обществознания (решение автора 07.11.2026). Это ровно TermAccess, как у
 * «Терминов» и «Верных суждений» (плюс ручная выдача в /admin/terms/access).
 */
class EnsureTaskTrainerAccess
{
    public function __construct(private TermAccess $access)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        if (! $request->user() || ! $this->access->allows($request->user())) {
            abort(403, 'Задания доступны ученикам курса обществознания.');
        }

        return $next($request);
    }
}
