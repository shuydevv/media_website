<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Что было с плеером на странице урока (TrialFeedbackController::progress,
 * служебные отметки event=ready|play|fallback): чтобы по человеку с нулём
 * просмотренных секунд было видно, не смотрел он или у него не загрузился
 * плеер с учётом просмотра (тогда показан обычный — без учёта).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_contacts', function (Blueprint $table) {
            foreach (['lesson', 'bonus'] as $slot) {
                $table->string("{$slot}_player_status", 8)->nullable(); // ready | fallback
                $table->timestamp("{$slot}_player_at")->nullable();
                $table->timestamp("{$slot}_played_at")->nullable(); // первый раз нажал play
            }
        });
    }

    public function down(): void
    {
        Schema::table('telegram_contacts', function (Blueprint $table) {
            $table->dropColumn(['lesson_player_status', 'lesson_player_at', 'lesson_played_at', 'bonus_player_status', 'bonus_player_at', 'bonus_played_at']);
        });
    }
};
