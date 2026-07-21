<?php

namespace App\Console\Commands;

use App\Models\Homework;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\HomeworkDueSoonNotification;
use App\Service\BillingService;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

class NotifyHomeworkDueSoon extends Command
{
    protected $signature = 'homeworks:notify-due-soon';
    protected $description = 'Уведомить студентов о домашках, дедлайн которых наступает в ближайшие сутки';

    public function handle(BillingService $billing): int
    {
        $homeworks = Homework::query()
            ->where('type', 'homework')
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [now(), now()->addDay()])
            ->with(['course', 'tasks', 'lesson.courseSession'])
            ->get()
            // Урок ещё не наступил — ученик не должен знать о домашке вообще,
            // тем более получать про неё напоминание (см. Homework::isLessonUpcoming()).
            ->reject(fn (Homework $hw) => $hw->isLessonUpcoming());

        $sent = 0;
        foreach ($homeworks as $hw) {
            if (!$hw->course) {
                continue;
            }

            foreach ($billing->activeStudentsWithAccess($hw->course) as $student) {
                $hasFinalSubmission = Submission::where('homework_id', $hw->id)
                    ->where('user_id', $student->id)
                    ->whereIn('status', ['checked', 'pending', 'expired'])
                    ->exists();
                if ($hasFinalSubmission) {
                    continue;
                }

                $alreadyNotified = DatabaseNotification::query()
                    ->where('notifiable_type', User::class)
                    ->where('notifiable_id', $student->id)
                    ->where('type', HomeworkDueSoonNotification::class)
                    ->where('data->homework_id', $hw->id)
                    ->exists();
                if ($alreadyNotified) {
                    continue;
                }

                $student->notify(new HomeworkDueSoonNotification($hw));
                $sent++;
            }
        }

        $this->info("Напоминаний о дедлайне домашки отправлено: {$sent}");

        return self::SUCCESS;
    }
}
