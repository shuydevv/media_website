<?php

namespace App\Notifications;

use App\Mail\NotificationMail;
use App\Models\Course;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class EnrolledInCourseNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const SLUG = 'enrolled_in_course';

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
            'Зачисление на курс',
            'mail.notifications.enrolled',
            [
                'studentName' => $notifiable->first_name ?? $notifiable->name,
                'courseTitle' => $this->course->title,
                'actionUrl' => route('student.courses.show', $this->course),
            ]
        ))->to($notifiable->email);
    }

    public function toArray($notifiable): array
    {
        return [
            'icon' => 'enrolled',
            'title' => 'Зачисление на курс',
            'body' => sprintf('Вы зачислены на курс «%s»', $this->course->title),
            'action_url' => route('student.courses.show', $this->course),
            'course_id' => $this->course->id,
        ];
    }
}
