<?php

namespace App\Notifications;

use App\Mail\HomeworkReviewedMail;
use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class HomeworkGradedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const SLUG = 'homework_graded';

    public function __construct(private Submission $submission, private ?string $mentorName = null)
    {
    }

    public function via($notifiable): array
    {
        return $notifiable->wantsNotification(self::SLUG) ? ['mail', 'database'] : [];
    }

    public function toMail($notifiable): HomeworkReviewedMail
    {
        // MailChannel не адресует Mailable сам (в отличие от MailMessage) —
        // ->to() обязателен здесь, иначе письмо падает без получателя.
        return (new HomeworkReviewedMail(
            studentName: $notifiable->first_name ?? $notifiable->name,
            assignmentTitle: $this->submission->homework->title ?? 'Домашняя работа',
            mentorName: $this->mentorName,
            score: (string) ($this->submission->total_score ?? ''),
            comment: null,
            linkToResult: route('student.submissions.show', $this->submission->id)
        ))->to($notifiable->email);
    }

    public function toArray($notifiable): array
    {
        return [
            'icon' => 'homework_graded',
            'title' => 'Домашка проверена',
            'body' => sprintf(
                '«%s» — %s баллов',
                $this->submission->homework->title ?? 'Домашняя работа',
                $this->submission->total_score ?? '0'
            ),
            'action_url' => route('student.submissions.show', $this->submission->id),
            'homework_id' => $this->submission->homework_id,
            'submission_id' => $this->submission->id,
        ];
    }
}
