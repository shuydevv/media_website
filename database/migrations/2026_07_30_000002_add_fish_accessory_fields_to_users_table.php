<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Вторая косметическая категория после фонов (см. 2026_07_21_000002/000003) —
// та же пара колонок (выбранный слаг + список открытых), тот же принцип:
// бесплатный дефолт + платные, открываются за корм. Реального арта пока нет,
// рендерится эмодзи-плейсхолдерами (см. FishFoodService::accessoryEmoji()).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('fish_accessory', 60)->nullable()->after('fish_unlocked_backgrounds');
            $table->json('fish_unlocked_accessories')->nullable()->after('fish_accessory');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['fish_accessory', 'fish_unlocked_accessories']);
        });
    }
};
