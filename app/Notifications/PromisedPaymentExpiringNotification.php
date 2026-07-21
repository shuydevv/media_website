<?php

namespace App\Notifications;

use App\Mail\NotificationMail;
use App\Models\Course;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PromisedPaymentExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const SLUG = 'promise_expiring';

    public function __construct(private Course $course, private CarbonInterface $expiresAt)
    {
    }

    public function via($notifiable): array
    {
        return $notifiable->wantsNotification(self::SLUG) ? ['mail', 'database'] : [];
    }

    public function toMail($notifiable): NotificationMail
    {
        return (new NotificationMail(
            'Обещанный платёж скоро истекает',
            'mail.notifications.promise_expiring',
            [
                'studentName' => $notifiable->first_name ?? $notifiable->name,
                'courseTitle' => $this->course->title,
                'expiresAt' => $this->expiresAt,
                'actionUrl' => route('student.billing.show'),
            ]
        ))->to($notifiable->email);
    }

    public function toArray($notifiable): array
    {
        return [
            'icon' => 'promise_expiring',
            'title' => 'Обещанный платёж скоро истекает',
            'body' => sprintf(
                'Доступ к «%s» по обещанному платежу истекает %s',
                $this->course->title,
                $this->expiresAt->format('d.m.Y H:i')
            ),
            'action_url' => route('student.billing.show'),
            'course_id' => $this->course->id,
        ];
    }
}
