<?php

namespace App\Notifications;

use App\Mail\NotificationMail;
use App\Models\Course;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PaymentOverdueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const SLUG = 'payment_overdue';

    public function __construct(private Course $course)
    {
    }

    public function via($notifiable): array
    {
        return $notifiable->wantsNotification(self::SLUG) ? ['mail', 'database'] : [];
    }

    public function toMail($notifiable): NotificationMail
    {
        return (new NotificationMail(
            'Доступ приостановлен — просрочена оплата',
            'mail.notifications.payment_overdue',
            [
                'studentName' => $notifiable->first_name ?? $notifiable->name,
                'courseTitle' => $this->course->title,
                'actionUrl' => route('billing.overdue', $this->course),
            ]
        ))->to($notifiable->email);
    }

    public function toArray($notifiable): array
    {
        return [
            'icon' => 'payment_overdue',
            'title' => 'Доступ приостановлен',
            'body' => sprintf('Оплата за «%s» просрочена, доступ приостановлен', $this->course->title),
            'action_url' => route('billing.overdue', $this->course),
            'course_id' => $this->course->id,
        ];
    }
}
