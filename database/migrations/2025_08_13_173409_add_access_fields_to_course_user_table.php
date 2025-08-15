<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_user', function (Blueprint $table) {
            // Активность записи на курс
            $table->boolean('is_active')->default(true)->after('user_id');

            // Окно временного доступа
            $table->timestamp('access_starts_at')->nullable()->after('is_active');
            $table->timestamp('access_expires_at')->nullable()->after('access_starts_at');

            // Индексы
            $table->index(['is_active']);
            $table->index(['access_starts_at']);
            $table->index(['access_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('course_user', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropIndex(['access_starts_at']);
            $table->dropIndex(['access_expires_at']);
            $table->dropColumn(['is_active', 'access_starts_at', 'access_expires_at']);
        });
    }
};
