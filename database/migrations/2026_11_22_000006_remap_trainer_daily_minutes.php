<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Нормы «Плана на сегодня» сменились с 10/15/25 на 10/20/30 мин
// (config('daily_plan.norms')): уже выбранные переводим на ближайшую новую,
// иначе в окне смены нормы ни одна плитка не была бы отмечена текущей.
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('trainer_daily_minutes', 15)->update(['trainer_daily_minutes' => 20]);
        DB::table('users')->where('trainer_daily_minutes', 25)->update(['trainer_daily_minutes' => 30]);
    }

    public function down(): void
    {
        // Обратно не переводим: 20 и 30 могли выбрать уже после смены.
    }
};
