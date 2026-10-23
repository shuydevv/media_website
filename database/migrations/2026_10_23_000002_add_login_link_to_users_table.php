<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Постоянная ссылка для входа без пароля (Профиль → Аккаунт, см.
// Auth\LoginLinkController). Поиск — по sha256 токена (login_token_hash),
// а сам токен лежит зашифрованным APP_KEY (cast 'encrypted'), чтобы ссылку
// можно было показывать в профиле снова, а дамп базы без ключа её не выдавал.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('login_token')->nullable()->after('remember_token');
            $table->string('login_token_hash', 64)->nullable()->unique()->after('login_token');
            $table->timestamp('login_token_used_at')->nullable()->after('login_token_hash');
            $table->string('login_token_used_agent', 255)->nullable()->after('login_token_used_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['login_token_hash']);
            $table->dropColumn(['login_token', 'login_token_hash', 'login_token_used_at', 'login_token_used_agent']);
        });
    }
};
