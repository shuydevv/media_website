<?php

namespace App\Notifications;

use App\Mail\NotificationMail;
use App\Models\Lesson;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class LessonRecordingAvailableNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const SLUG = 'recording_available';

    public function __construct(private Lesson $lesson)
    {
    }

    public function via($notifiable): array
    {
        return $notifiable->wantsNotification(self::SLUG) ? ['mail', 'database'] : [];
    }

    public function toMail($notifiable): NotificationMail
    {
        return (new NotificationMail(
            'Появилась запись урока',
            'mail.notifications.recording_available',
            [
                'studentName' => $notifiable->first_name ?? $notifiable->name,
                'lessonTitle' => $this->lesson->title,
                'actionUrl' => route('student.lessons.show', $this->lesson),
            ]
        ))->to($notifiable->email);
    }

    public function toArray($notifiable): array
    {
        return [
            'icon' => 'recording',
            'title' => 'Появилась запись урока',
            'body' => sprintf('Запись урока «%s» уже доступна', $this->lesson->title),
            'action_url' => route('student.lessons.show', $this->lesson),
            'lesson_id' => $this->lesson->id,
        ];
    }
}
