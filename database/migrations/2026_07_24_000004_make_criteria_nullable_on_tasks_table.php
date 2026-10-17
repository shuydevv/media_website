<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * criteria было NOT NULL, потому что раньше Task создавался ТОЛЬКО через
 * форму критериев (criteria было обязательным полем). Теперь задание в
 * банке может быть создано через форму содержания без единого слова о
 * критериях (особенно для авто-проверяемых типов, где ручных критериев для
 * куратора вообще не нужно) — критерии заполняются отдельно и не всегда
 * сразу.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tasks') || !Schema::hasColumn('tasks', 'criteria')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            // Тесты на SQLite (phpunit.xml): MODIFY — синтаксис MySQL.
            Schema::table('tasks', fn (Blueprint $table) => $table->text('criteria')->nullable()->change());

            return;
        }

        DB::statement('ALTER TABLE tasks MODIFY criteria TEXT NULL');
    }

    public function down(): void
    {
        if (!Schema::hasTable('tasks') || !Schema::hasColumn('tasks', 'criteria')) {
            return;
        }

        DB::statement("UPDATE tasks SET criteria = '' WHERE criteria IS NULL");
        DB::statement('ALTER TABLE tasks MODIFY criteria TEXT NOT NULL');
    }
};
