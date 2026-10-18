<?php

namespace App\Console\Commands;

use App\Models\TermSession;
use App\Models\User;
use App\Models\UserTerm;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ShiftTermProgress extends Command
{
    protected $signature = 'terms:shift
        {user : Email или ID ученика}
        {days=1 : На сколько дней назад сдвинуть}
        {--reset : Вместо сдвига стереть весь прогресс ученика в тренажёре}';

    protected $description = 'Сдвигает прогресс ученика в тренажёре терминов в прошлое — чтобы проверить «следующий день», не дожидаясь его';

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

        if ($this->option('reset')) {
            UserTerm::where('user_id', $user->id)->delete();
            TermSession::where('user_id', $user->id)->delete();
            $this->info("Готово: прогресс {$user->email} в тренажёре стёрт (корм не тронут).");

            return self::SUCCESS;
        }

        $days = max(1, (int) $this->argument('days'));

        // Сдвигается всё, что привязано к дате: сроки повторения и дни
        // сессий — иначе «сегодняшняя» порция осталась бы сегодняшней.
        DB::transaction(function () use ($user, $days) {
            foreach (UserTerm::where('user_id', $user->id)->get() as $row) {
                $row->due_on = $row->due_on->subDays($days);
                $row->learned_on = $row->learned_on->subDays($days);
                $row->last_reviewed_on = $row->last_reviewed_on?->subDays($days);
                $row->save();
            }
            foreach (TermSession::where('user_id', $user->id)->get() as $session) {
                $session->day = $session->day->subDays($days);
                $session->save();
            }
        });

        $due = UserTerm::where('user_id', $user->id)->whereDate('due_on', '<=', now()->toDateString())->count();
        $this->info("Готово: {$user->email} — сдвиг на {$days} дн., ждут повторения: {$due}.");

        return self::SUCCESS;
    }
}
