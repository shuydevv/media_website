<?php

namespace App\Notifications;

use App\Mail\NotificationMail;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PaymentConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const SLUG = 'payment_confirmed';

    public function __construct(private Payment $payment)
    {
    }

    public function via($notifiable): array
    {
        return $notifiable->wantsNotification(self::SLUG) ? ['mail', 'database'] : [];
    }

    private function amountRub(): string
    {
        return number_format($this->payment->amount_cents / 100, 2, '.', ' ');
    }

    public function toMail($notifiable): NotificationMail
    {
        return (new NotificationMail(
            'Платёж зафиксирован',
            'mail.notifications.payment_confirmed',
            [
                'studentName' => $notifiable->first_name ?? $notifiable->name,
                'courseTitle' => $this->payment->course->title ?? 'Курс',
                'amountRub' => $this->amountRub(),
                'actionUrl' => route('student.billing.show'),
            ]
        ))->to($notifiable->email);
    }

    public function toArray($notifiable): array
    {
        return [
            'icon' => 'payment_confirmed',
            'title' => 'Платёж зафиксирован',
            'body' => sprintf(
                'Оплата за «%s» на сумму %s ₽ получена',
                $this->payment->course->title ?? 'Курс',
                $this->amountRub()
            ),
            'action_url' => route('student.billing.show'),
            'payment_id' => $this->payment->id,
            'course_id' => $this->payment->course_id,
        ];
    }
}
