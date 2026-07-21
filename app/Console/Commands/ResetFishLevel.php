<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ResetFishLevel extends Command
{
    protected $signature = 'fish:reset
        {user=demo.student@example.com : Email или ID ученика}
        {--corm=15 : Сколько корма сразу выдать после сброса (для быстрого тестирования левел-апа)}';

    protected $description = 'Сбрасывает прогресс маскота-рыбы ученика до уровня 1 — для тестирования анимаций';

    public function handle(): int
    {
        $identifier = $this->argument('user');
        $user = is_numeric($identifier)
            ? User::find($identifier)
            : User::where('email', $identifier)->first();

        if (!$user) {
            $this->error("Пользователь \"{$identifier}\" не найден.");

            return self::FAILURE;
        }

        $user->fish_total_fed = 0;
        $user->fish_corm_balance = max(0, (int) $this->option('corm'));
        $user->fish_streak_count = 0;
        $user->fish_last_active_date = null;
        $user->fish_milestones = null;
        $user->save();

        $this->info("Готово: {$user->email} — уровень 1, корм: {$user->fish_corm_balance}.");

        return self::SUCCESS;
    }
}
