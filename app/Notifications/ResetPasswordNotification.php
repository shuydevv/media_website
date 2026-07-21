<?php

namespace App\Notifications;

use App\Mail\NotificationMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue; // если хочешь через очередь — оставь
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification /* implements ShouldQueue */
{
    use Queueable;

    public function __construct(public string $token) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): NotificationMail
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new NotificationMail(
            'Сброс пароля на платформе ЕГЭ',
            'mail.auth.password_reset',
            ['url' => $url]
        ))->to($notifiable->email);
    }
}
