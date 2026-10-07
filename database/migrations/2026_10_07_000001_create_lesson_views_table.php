<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Одна строка на (ученик, урок, вид материала) — обновляется отметками
     * плеера, а не дописывается: таблица растёт с числом учеников × уроков,
     * а не с числом отметок (см. Student\LessonWatchController).
     * kind: recording — запись эфира, short — «сок», live — сам эфир,
     * notes — конспект (тут только факт открытия, секунды всегда 0).
     */
    public function up(): void
    {
        Schema::create('lesson_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->string('kind', 16);
            $table->unsignedInteger('watched_seconds')->default(0);
            $table->unsignedInteger('max_position_seconds')->default(0);
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamp('first_watched_at')->nullable();
            $table->timestamp('last_watched_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'lesson_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_views');
    }
};
