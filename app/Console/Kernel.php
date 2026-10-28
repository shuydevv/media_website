<?php

namespace App\Console;

use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }

    protected function schedule(\Illuminate\Console\Scheduling\Schedule $schedule): void
    {
        $schedule->command('enrollments:expire')->dailyAt('03:00');
        $schedule->command('billing:send-reminders')->dailyAt('08:00');
        $schedule->command('billing:notify-overdue')->dailyAt('08:15');
        $schedule->command('billing:notify-promise-expiring')->dailyAt('08:30');
        $schedule->command('crm:notify-reminders-due')->dailyAt('08:45');
        $schedule->command('homeworks:notify-due-soon')->dailyAt('09:00');
        $schedule->command('lessons:notify-starting-soon')->everyFifteenMinutes();
        $schedule->command('queue:monitor-health')->everyFiveMinutes();
        $schedule->command('sessions:prune')->dailyAt('04:30');
        $schedule->command('ai:collect-grading-batches')->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('telegram:send-reminders')->everyMinute()->withoutOverlapping();
        // Без withoutOverlapping (мьютекс — файловый кэш): от двойного старта
        // и двойной отправки защищают блокировка и захват получателя в БД.
        $schedule->command('telegram:broadcasts')->everyMinute();
        $schedule->command('telegram:digest')->dailyAt('21:00');
        $schedule->command('telegram:remind-unanswered')->everyTenMinutes()->withoutOverlapping();
        // Без withoutOverlapping: его мьютекс в файловом кэше, а команда как
        // раз страхует от сбоев кэша; от двойной досылки — свой claim в БД.
        $schedule->command('telegram:resend-unforwarded')->everyMinute();
    }
}
