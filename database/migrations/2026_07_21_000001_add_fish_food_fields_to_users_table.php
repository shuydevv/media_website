<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('fish_corm_balance')->default(0)->after('avatar');
            $table->unsignedInteger('fish_total_fed')->default(0)->after('fish_corm_balance');
            $table->unsignedSmallInteger('fish_streak_count')->default(0)->after('fish_total_fed');
            $table->date('fish_last_active_date')->nullable()->after('fish_streak_count');
            $table->json('fish_milestones')->nullable()->after('fish_last_active_date');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'fish_corm_balance',
                'fish_total_fed',
                'fish_streak_count',
                'fish_last_active_date',
                'fish_milestones',
            ]);
        });
    }
};
