<?php

namespace App\Notifications;

use App\Mail\PaymentReminderMail;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PaymentDueSoonNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const SLUG = 'payment_due_soon';

    public function __construct(
        private string $studentName,
        private string $courseTitle,
        private CarbonInterface $dueAt,
    ) {
    }

    public function via($notifiable): array
    {
        return $notifiable->wantsNotification(self::SLUG) ? ['mail', 'database'] : [];
    }

    public function toMail($notifiable): PaymentReminderMail
    {
        return (new PaymentReminderMail($this->studentName, $this->courseTitle, $this->dueAt))
            ->to($notifiable->email);
    }

    public function toArray($notifiable): array
    {
        return [
            'icon' => 'payment_due',
            'title' => 'Скоро оплата',
            'body' => sprintf('Оплата за «%s» — %s', $this->courseTitle, $this->dueAt->format('d.m.Y')),
            'action_url' => route('student.billing.show'),
        ];
    }
}
