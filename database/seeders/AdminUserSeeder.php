<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');
        $name = env('ADMIN_NAME', 'Администратор');

        if (!$email) {
            $this->command->warn('ADMIN_EMAIL не задан — пропускаем создание админа.');
            return;
        }

        if (!$password) {
            // сгенерим разовый пароль, если не задан
            $password = Str::random(16);
            $this->command->warn("ADMIN_PASSWORD не задан. Сгенерирован временный пароль: {$password}");
        }

        $user = User::updateOrCreate(
            ['email' => mb_strtolower($email)],
            [
                'name'               => $name,
                'first_name'         => 'Админ',
                'last_name'          => 'Система',
                'password'           => Hash::make($password),
                'locale'             => config('app.locale'),
                'timezone'           => config('app.timezone'),
                'email_verified_at'  => now(), // сразу верифицирован
            ]
        );

        // Назначаем роль Admin (поддержка двух вариантов: колонка role или spatie/permission)
        if (method_exists($user, 'assignRole')) {
            // spatie/laravel-permission
            $user->assignRole('Admin');
        } else {
            // простая колонка role
            if (property_exists($user, 'fillable') && in_array('role', $user->getFillable())) {
                $user->role = 'Admin';
                $user->save();
            }
        }

        $this->command->info("Админ-пользователь гарантирован: {$user->email}");
    }
}
