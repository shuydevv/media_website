<?php

namespace App\Service;

use App\Mail\User\InviteLinkMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Общий код для отправки/переотправки ссылки-приглашения — используется и
 * при создании пользователя (Admin\User\StoreController), и при повторной
 * отправке (Admin\User\InviteController), чтобы не дублировать генерацию
 * подписанной ссылки в двух местах.
 */
class UserInviteService
{
    public const DAYS_VALID = 7;

    /**
     * @return string Подписанная ссылка — вызывающий код может её показать
     * админу для копирования (например, если письмо не дошло и проще
     * отправить ученику вручную через мессенджер).
     */
    public function send(User $user): string
    {
        $loginUrl = URL::temporarySignedRoute(
            'auth.invite.link',
            now()->addDays(self::DAYS_VALID),
            ['id' => $user->id]
        );

        Mail::to($user->email)->send(new InviteLinkMail($loginUrl, self::DAYS_VALID));

        return $loginUrl;
    }
}
