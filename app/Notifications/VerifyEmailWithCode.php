<?php

namespace App\Notifications;

use App\Mail\NotificationMail;
use App\Service\EmailOtpService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;

class VerifyEmailWithCode extends Notification implements ShouldQueue
{
    use Queueable;

    // $vid — необязательный: старые уже поставленные в очередь письма (до этого
    // изменения) не должны падать при десериализации без него.
    public function __construct(public string $code, public int $userId, public ?string $vid = null)
    {
    }

    // Вызывается Laravel, когда джоба окончательно провалилась после исчерпания
    // всех --tries воркера (не на каждой промежуточной попытке) — см.
    // EmailOtpService::deliveryStatus()/статус-поллинг на странице ввода кода.
    public function failed(\Throwable $exception): void
    {
        if ($this->vid) {
            Cache::put(app(EmailOtpService::class)->statusKey($this->vid), 'failed', now()->addMinutes(15));
        }
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

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
        ))->to($notifiable->routeNotificationFor('mail'));
    }
}
