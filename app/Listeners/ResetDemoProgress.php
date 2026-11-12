<?php

namespace App\Listeners;

use App\Models\User;
use App\Service\Demo\DemoMode;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

/**
 * Прогресс демо-ученика не настоящий: стирается при выходе. При входе —
 * тоже, на случай если в прошлый раз выйти забыли (закрыли вкладку).
 *
 * Вход — только через форму /login: Login-событие бывает и при входе по
 * cookie «запомнить меня», когда протухла сессия, — сброс посреди показа
 * там не нужен.
 */
class ResetDemoProgress
{
    public function __construct(private DemoMode $demo)
    {
    }

    public function handle(Login|Logout $event): void
    {
        $user = $event->user;
        if (! $user instanceof User || ! $user->is_demo) {
            return;
        }

        // По пути, не по имени роута: у POST /login из Auth::routes() имени нет.
        if ($event instanceof Login && ! (request()->isMethod('post') && request()->is('login'))) {
            return;
        }

        $this->demo->reset($user);
    }
}
