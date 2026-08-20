<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;

class EnforceSessionLimit
{
    /**
     * Не более 3 одновременных сессий у ученика: при входе с нового устройства
     * убиваем самые старые по активности "чужие" сессии, оставляя новую + две свежие.
     *
     * Было 2 (текущая + 1 старая) — этого хватало, чтобы вход с третьего места
     * (например, просто открытая в новой вкладке вкладка после re-login по
     * remember-me куке из PhoneAuthController) тут же убивал сессию, в которой
     * ученик мог как раз дописывать развёрнутый ответ в визарде домашки: строка
     * сессии удалялась из БД, CSRF-токен на уже открытой странице переставал
     * совпадать ни с чем на сервере, и следующий сабмит падал в 419 Page Expired,
     * теряя набранный текст. 3 — тот же анти-шеринг-аккаунтом смысл, но с запасом
     * под "ноутбук + телефон + случайно открытая вторая вкладка".
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

        $toDelete = $otherSessionIds->slice(2);

        if ($toDelete->isNotEmpty()) {
            DB::table('sessions')->whereIn('id', $toDelete)->delete();
        }
    }
}
