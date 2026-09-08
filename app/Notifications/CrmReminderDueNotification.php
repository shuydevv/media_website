<?php

namespace App\Notifications;

use App\Mail\NotificationMail;
use App\Models\CrmReminder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Уходит админу, создавшему напоминание в /admin/crm — не студенту, поэтому
 * без гейта на NotificationPreferenceRegistry (тот список — только
 * студенческие настройки в профиле, к внутренним админским уведомлениям
 * отношения не имеет).
 */
class CrmReminderDueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private CrmReminder $reminder)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): NotificationMail
    {
        $student = $this->reminder->user;
        $studentName = trim(($student->first_name ?? '').' '.($student->last_name ?? '')) ?: ($student->name ?? 'ученик');

        return (new NotificationMail(
            'Напоминание по ученику: '.$studentName,
            'mail.notifications.crm_reminder_due',
            [
                'studentName' => $studentName,
                'note' => $this->reminder->note,
                'dueAt' => $this->reminder->due_at,
                'actionUrl' => route('admin.user.show', $student->id),
            ]
        ))->to($notifiable->email);
    }
}
