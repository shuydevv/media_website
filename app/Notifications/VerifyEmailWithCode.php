<?php
namespace App\Notifications;

use App\Mail\NotificationMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyEmailWithCode extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $code, public int $userId) {}

    public function via($notifiable): array { return ['mail']; }

    public function toMail($notifiable): NotificationMail
    {
        $url = URL::temporarySignedRoute(
            'auth.email.link',
            now()->addMinutes(60),
            ['id' => $this->userId]
        );

        return (new NotificationMail(
            'Подтвердите e-mail',
            'mail.auth.verify_email_code',
            ['code' => $this->code, 'url' => $url]
        ))->to($notifiable->email);
    }
}
