<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
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
        $schedule->command('homeworks:notify-due-soon')->dailyAt('09:00');
        $schedule->command('lessons:notify-starting-soon')->everyFifteenMinutes();
    }

}
