<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * avito_accounts — аккаунты Авито, подключённые к теме «🛒 Авито» (админка
 * «Telegram-бот → Авито»). Ключи были одни, в .env (AVITO_CLIENT_ID/SECRET), —
 * если они заданы, переносим их сюда первым аккаунтом. avito_user_id у него
 * пустой: заполнится при первом обращении (AvitoAccount::forUser) или
 * кнопкой «Проверить» в админке.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avito_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('client_id', 100)->unique();
            $table->text('client_secret'); // зашифрован (cast encrypted)
            // id и имя профиля на Авито — из /core/v1/accounts/self
            $table->unsignedBigInteger('avito_user_id')->nullable()->unique();
            $table->string('profile_name')->nullable();
            $table->timestamp('subscribed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        $id = config('services.avito.client_id');
        $secret = config('services.avito.client_secret');
        if (filled($id) && filled($secret)) {
            DB::table('avito_accounts')->insert([
                'name' => 'Основной аккаунт',
                'client_id' => $id,
                'client_secret' => Crypt::encryptString($secret),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('avito_accounts');
    }
};
