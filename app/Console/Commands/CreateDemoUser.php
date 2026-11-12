<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Service\Demo\DemoMode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Заводит (или обновляет) демо-ученика для показа курса — см.
 * App\Service\Demo\DemoMode и config/demo.php. Курсы ему назначаются как
 * обычному ученику: /admin/users/{id}/edit.
 */
class CreateDemoUser extends Command
{
    protected $signature = 'demo:create
        {email=demo@poltavskiy : Логин демо-ученика для /login}
        {--password= : Пароль (обязателен при создании; при обновлении — по желанию)}';

    protected $description = 'Создаёт демо-ученика для показа курса покупателям и ставит стартовый прогресс';

    public function handle(DemoMode $demo): int
    {
        $email = (string) $this->argument('email');
        $password = (string) $this->option('password');

        $user = User::where('email', $email)->first();

        if ($user && ! $user->is_demo) {
            $this->error("{$email} — настоящий аккаунт, демо из него не делаю.");

            return self::FAILURE;
        }

        if (! $user && $password === '') {
            $this->error('Укажите пароль: --password=...');

            return self::FAILURE;
        }

        $user ??= new User();
        $user->forceFill([
            'name' => 'Демо-ученик',
            'email' => $email,
            'role' => User::ROLE_READER,
            'is_demo' => true,
            // Анкета «заполни профиль» на показе не нужна. Почту не
            // подтверждаем: адрес ненастоящий, писем ему не шлём
            // (SkipDemoNotifications).
            'profile_completed_at' => $user->profile_completed_at ?? now(),
        ]);
        if ($password !== '') {
            $user->password = Hash::make($password);
        }
        $user->save();

        $demo->reset($user);

        $this->info("Готово: {$email} (id {$user->id}). Курсы назначьте в /admin/users/{$user->id}/edit.");

        return self::SUCCESS;
    }
}
