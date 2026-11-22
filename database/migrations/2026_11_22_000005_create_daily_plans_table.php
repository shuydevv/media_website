<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// «План на сегодня» в «Качалке» (docs/trainers/План дня — механика.md):
// норма ученика в минутах и снимок плана на день — план собирается при
// первом заходе за день и до конца дня не растёт.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // null — норму ещё не выбрали, плана нет.
            $table->unsignedSmallInteger('trainer_daily_minutes')->nullable()->after('fish_unlocked_foods');
        });

        Schema::create('daily_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('day');
            // Бюджет дня в минутах (уже с поправкой на облегчённый день).
            $table->unsignedSmallInteger('minutes');
            // Облегчённый день: lesson / homework, null — обычный.
            $table->string('light', 20)->nullable();
            // [{key, kind, situation, quota, sec, done_at}]
            $table->json('items');
            // Норму поменяли — несделанное нужно собрать заново.
            $table->boolean('stale')->default(false);
            // Для будущих достижений («закрыл план N дней подряд»).
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_plans');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('trainer_daily_minutes');
        });
    }
};
