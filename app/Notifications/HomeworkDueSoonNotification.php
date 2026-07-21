<?php

namespace App\Notifications;

use App\Mail\NotificationMail;
use App\Models\Homework;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class HomeworkDueSoonNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const SLUG = 'homework_due_soon';

    public function __construct(private Homework $homework)
    {
    }

    public function via($notifiable): array
    {
        return $notifiable->wantsNotification(self::SLUG) ? ['mail', 'database'] : [];
    }

    public function toMail($notifiable): NotificationMail
    {
        // MailChannel не адресует Mailable сам (в отличие от MailMessage) —
        // ->to() обязателен здесь, иначе письмо падает без получателя.
        return (new NotificationMail(
            'Скоро дедлайн домашки',
            'mail.notifications.homework_due_soon',
            [
                'studentName' => $notifiable->first_name ?? $notifiable->name,
                'homeworkTitle' => $this->homework->title,
                'dueAt' => $this->homework->due_at,
                'actionUrl' => route('student.submissions.create', $this->homework),
            ]
        ))->to($notifiable->email);
    }

    public function toArray($notifiable): array
    {
        return [
            'icon' => 'homework_due',
            'title' => 'Скоро дедлайн домашки',
            'body' => sprintf(
                '«%s» — сдать до %s',
                $this->homework->title,
                $this->homework->due_at?->format('d.m.Y H:i')
            ),
            'action_url' => route('student.submissions.create', $this->homework),
            'homework_id' => $this->homework->id,
        ];
    }
}
