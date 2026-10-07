<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * История активности ученика по дням — users.fish_last_active_date
     * хранит только одну последнюю дату (и только визит на дашборд), по ней
     * не видно ни пропавших недель, ни "занимается только в ночь перед
     * дедлайном". active_slots — число пятиминутных отрезков дня, в которые
     * от ученика был хотя бы один запрос (см. TrackStudentActivity), то есть
     * примерно "минут на платформе / 5".
     */
    public function up(): void
    {
        Schema::create('user_activity_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedSmallInteger('active_slots')->default(0);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();

            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_activity_days');
    }
};
