<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Полосы «Сытость» и «Радость» у акулы (см. App\Service\FishMood). Хранится
 * значение на момент *_at, текущее считается при показе — без крона.
 * Всем существующим ученикам — 100% с момента выкладки: иначе в первый же
 * день у большинства акула была бы грустной.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('fish_satiety', 5, 2)->default(100)->after('fish_total_fed');
            $table->timestamp('fish_satiety_at')->nullable()->after('fish_satiety');
            $table->decimal('fish_joy', 5, 2)->default(100)->after('fish_satiety_at');
            $table->timestamp('fish_joy_at')->nullable()->after('fish_joy');
        });

        DB::table('users')->update(['fish_satiety_at' => now(), 'fish_joy_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['fish_satiety', 'fish_satiety_at', 'fish_joy', 'fish_joy_at']);
        });
    }
};
