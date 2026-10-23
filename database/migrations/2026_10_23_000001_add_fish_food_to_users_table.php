<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Третья косметическая категория после фонов и аксессуаров (см.
// 2026_07_30_000002) — еда, которой кормят акулу. Та же пара колонок
// (выбранный слаг + список открытых); null = config('fish.default_food').
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('fish_food', 60)->nullable()->after('fish_unlocked_accessories');
            $table->json('fish_unlocked_foods')->nullable()->after('fish_food');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['fish_food', 'fish_unlocked_foods']);
        });
    }
};
