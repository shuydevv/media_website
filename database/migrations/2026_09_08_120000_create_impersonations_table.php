<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Аудит-лог входов админа под учеником (Admin\User\ImpersonateController /
 * ImpersonationLeaveController). ended_at = null, пока сеанс просмотра не
 * закрыт явно кнопкой "Вернуться в аккаунт администратора" — как и с
 * последним входом в User (см. её комментарий), не пытаемся поймать закрытие
 * вкладки/протухание сессии, это не критично для аудита.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impersonations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id');
            $table->unsignedBigInteger('user_id');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->foreign('admin_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impersonations');
    }
};
