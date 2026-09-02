<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Глобальная (не per-route) миддлварь — student-роуты в routes/web.php
 * раскиданы по ~8 отдельным мелким Route::group() без единого общего
 * префикса-группы (см. CLAUDE.md про этот файл), вписывать сюда per-route
 * было бы легко забыть один из них и оставить дыру в гейте. Здесь наоборот:
 * пропускаем всех, кроме "залогинен, студент, профиль не завершён" — то
 * есть безопасна по умолчанию для гостей/admin/mentor/уже онбордившихся.
 *
 * Требует бэкфилла profile_completed_at для существующих пользователей
 * ДО включения — иначе действующие ученики без этого поля (оно раньше
 * нигде не проставлялось) все разом упрутся в онбординг на следующем визите.
 */
class EnsureProfileCompleted
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || !$user->isStudent() || $user->profile_completed_at) {
            return $next($request);
        }

        if ($request->routeIs('onboarding.profile.*') || $request->routeIs('logout')) {
            return $next($request);
        }

        return redirect()->route('onboarding.profile.show');
    }
}
