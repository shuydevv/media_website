<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Уровень, который человек выбрал после класса (config diagnostic.tracks):
    // basic — начальный тест, middle — средний. null — ещё не выбрал.
    public function up(): void
    {
        Schema::table('diagnostic_attempts', function (Blueprint $table) {
            $table->string('track', 8)->nullable()->after('grade');
        });
    }

    public function down(): void
    {
        Schema::table('diagnostic_attempts', function (Blueprint $table) {
            $table->dropColumn('track');
        });
    }
};
