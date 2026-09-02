<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Service\UserInviteService;

class InviteController extends Controller
{
    /**
     * Повторная отправка ссылки-приглашения — она же единственный способ
     * восстановить доступ ученику, который потерял сессию/сменил устройство
     * до того, как задал себе пароль (EmailAuthController::inviteLogin()).
     * После profile_completed_at ссылка всё равно ничего не даст (гейт
     * редиректит на /login), поэтому явно не даём отправить её вхолостую.
     */
    public function __invoke(User $user, UserInviteService $invite) {
        if ($user->profile_completed_at) {
            return back()->with('error', 'Пользователь уже завершил регистрацию — используйте восстановление пароля вместо повторного приглашения.');
        }

        $inviteUrl = $invite->send($user);

        return back()
            ->with('success', 'Письмо с приглашением отправлено повторно.')
            ->with('inviteUrl', $inviteUrl);
    }
}
