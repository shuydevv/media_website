<?php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;

class VerifyEmailWithCode extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $code, public int $userId) {}

    public function via($notifiable): array { return ['mail']; }

    public function toMail($notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute(
            'auth.email.link',
            now()->addMinutes(60),
            ['id' => $this->userId]
        );

        return (new MailMessage)
            ->subject('Подтвердите e-mail')
            ->greeting('Привет!')
            ->line('Ваш код подтверждения:')
            ->line("**{$this->code}**")
            ->line('Код действует 15 минут. Либо нажмите кнопку ниже:')
            ->action('Подтвердить e-mail', $url)
            ->line('Если вы не запрашивали регистрацию, просто проигнорируйте это письмо.');
    }
}
