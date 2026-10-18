<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * last_failed — последняя проверка термина закончилась ошибкой. По нему
     * тренажёр показывает ученику список «Стоит перечитать»; сбрасывается
     * первым же верным повторением. Не счётчик ошибок за всё время: давно
     * выученный термин с десятью старыми ошибками слабым не считается.
     */
    public function up(): void
    {
        Schema::table('user_terms', function (Blueprint $table) {
            $table->boolean('last_failed')->default(false)->after('next_method');
        });

        // Формат term_sessions.state поменялся до первого релиза (очередь
        // шагов вместо очереди id + фаз). Начатые на старом формате сессии
        // продолжить нельзя — убираем, прогресс в user_terms не страдает.
        DB::table('term_sessions')->whereNull('finished_at')->delete();
    }

    public function down(): void
    {
        Schema::table('user_terms', function (Blueprint $table) {
            $table->dropColumn('last_failed');
        });
    }
};
