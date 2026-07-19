<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;

class EnforceSessionLimit
{
    /**
     * Не более 2 одновременных сессий у ученика: при входе с нового устройства
     * убиваем самые старые по активности "чужие" сессии, оставляя новую + одну свежую.
     */
    public function handle(Login $event): void
    {
        $user = $event->user;

        if (!method_exists($user, 'isStudent') || !$user->isStudent()) {
            return;
        }

        $currentId = session()->getId();

        $otherSessionIds = DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentId)
            ->orderByDesc('last_activity')
            ->pluck('id');

        $toDelete = $otherSessionIds->slice(1);

        if ($toDelete->isNotEmpty()) {
            DB::table('sessions')->whereIn('id', $toDelete)->delete();
        }
    }
}
