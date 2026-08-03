<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Серия домашек "подряд вовремя" убрана из системы начисления корма —
// отстающие ученики не должны терять весь прогресс из-за одного опоздания
// (см. FishFoodService::awardHomeworkCompletion()). Колонка нигде не
// отображается во вьюхах, дроп безопасен.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('fish_streak_count');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('fish_streak_count')->default(0)->after('fish_total_fed');
        });
    }
};
