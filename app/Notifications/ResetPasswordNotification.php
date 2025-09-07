<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue; // если хочешь через очередь — оставь
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification /* implements ShouldQueue */
{
    use Queueable;

    public function __construct(public string $token) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject('Сброс пароля на платформе ЕГЭ')
            ->greeting('Привет!')
            ->line('Вы запросили ссылку для сброса пароля.')
            ->action('Сбросить пароль', $url)
            ->line('Если вы не запрашивали сброс, просто игнорируйте это письмо.');
    }
}
