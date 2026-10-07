<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\UserActivityDay;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Пишет историю активности ученика по дням (user_activity_days) для отчёта
 * в /admin/users/{id}. В БД ходит не на каждый запрос, а максимум раз в
 * UserActivityDay::SLOT_MINUTES минут на ученика — Cache::add() пропускает
 * только первый запрос отрезка, остальные отсекаются без записи. Поэтому
 * active_slots — это число "активных пятиминуток" за день, а не число
 * запросов.
 */
class TrackStudentActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();

        // Вход админа под учеником (Admin\User\ImpersonateController) —
        // не активность самого ученика, в его историю не пишем.
        if (! $user || (int) $user->role !== User::ROLE_READER || $request->session()->has('impersonator_id')) {
            return $response;
        }

        $now = now();
        $slot = intdiv($now->hour * 60 + $now->minute, UserActivityDay::SLOT_MINUTES);
        $key = "activity:{$user->id}:{$now->toDateString()}:{$slot}";

        if (! Cache::add($key, 1, UserActivityDay::SLOT_MINUTES * 60)) {
            return $response;
        }

        try {
            DB::table('user_activity_days')->upsert(
                [[
                    'user_id' => $user->id,
                    'date' => $now->toDateString(),
                    'active_slots' => 1,
                    'first_seen_at' => $now,
                    'last_seen_at' => $now,
                ]],
                ['user_id', 'date'],
                ['active_slots' => DB::raw('active_slots + 1'), 'last_seen_at' => $now]
            );
        } catch (\Throwable $e) {
            // Аналитика не должна ронять запрос ученика.
            report($e);
        }

        return $response;
    }
}
