<?php

namespace App\Notifications;

use App\Mail\NotificationMail;
use App\Models\CourseSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class LessonStartingSoonNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const SLUG = 'lesson_starting_soon';

    public function __construct(private CourseSession $session)
    {
    }

    public function via($notifiable): array
    {
        return $notifiable->wantsNotification(self::SLUG) ? ['mail', 'database'] : [];
    }

    private function actionUrl(): string
    {
        return $this->session->lesson
            ? route('student.lessons.show', $this->session->lesson)
            : route('student.dashboard');
    }

    public function toMail($notifiable): NotificationMail
    {
        return (new NotificationMail(
            'Урок скоро начнётся',
            'mail.notifications.lesson_starting_soon',
            [
                'studentName' => $notifiable->first_name ?? $notifiable->name,
                'courseTitle' => $this->session->course->title ?? 'Курс',
                'startAt' => $this->session->start_date_time,
                'actionUrl' => $this->actionUrl(),
            ]
        ))->to($notifiable->email);
    }

    public function toArray($notifiable): array
    {
        return [
            'icon' => 'lesson_soon',
            'title' => 'Урок скоро начнётся',
            'body' => sprintf(
                'Урок по курсу «%s» начнётся в %s',
                $this->session->course->title ?? 'Курс',
                $this->session->start_date_time?->format('H:i')
            ),
            'action_url' => $this->actionUrl(),
            'session_id' => $this->session->id,
        ];
    }
}
