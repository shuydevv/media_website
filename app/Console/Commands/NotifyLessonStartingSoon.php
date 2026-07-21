<?php

namespace App\Console\Commands;

use App\Models\CourseSession;
use App\Notifications\LessonStartingSoonNotification;
use App\Service\BillingService;
use Illuminate\Console\Command;

class NotifyLessonStartingSoon extends Command
{
    protected $signature = 'lessons:notify-starting-soon';
    protected $description = 'Уведомить студентов о занятиях, которые начнутся в ближайшие 30 минут';

    public function handle(BillingService $billing): int
    {
        // start_date_time — computed-аксессор (date + start_time), не колонка,
        // поэтому окно по времени фильтруем в PHP; в БД сужаем только по дате,
        // чтобы не тащить весь стол.
        $candidates = CourseSession::query()
            ->visible()
            ->whereNull('notified_starting_soon_at')
            ->whereBetween('date', [now()->toDateString(), now()->addDay()->toDateString()])
            ->with('course')
            ->get()
            ->filter(function (CourseSession $session) {
                $start = $session->start_date_time;
                return $start !== null && $start->between(now(), now()->addMinutes(30));
            });

        $sent = 0;
        foreach ($candidates as $session) {
            if (!$session->course) {
                continue;
            }

            foreach ($billing->activeStudentsWithAccess($session->course) as $student) {
                $student->notify(new LessonStartingSoonNotification($session));
                $sent++;
            }

            $session->update(['notified_starting_soon_at' => now()]);
        }

        $this->info("Уведомлений о скором начале урока отправлено: {$sent} (сессий: {$candidates->count()})");

        return self::SUCCESS;
    }
}
