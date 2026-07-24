<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Баллы за задание — как и критерии, свойство НОМЕРА в экзамене
 * (категория + номер), не конкретного вопроса в банке: один и тот же
 * номер всегда оценивается одинаково, незачем указывать баллы у каждого
 * отдельного задания с этим номером. Переносим max_score с tasks на
 * task_criteria — тот же ход, что уже применялся для criteria/
 * ai_rationale_template/comment (см. 2026_07_24_000006/000007).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('task_criteria', 'max_score')) {
            Schema::table('task_criteria', function (Blueprint $table) {
                $table->unsignedSmallInteger('max_score')->default(1)->after('number');
            });
        }

        if (Schema::hasColumn('tasks', 'max_score')) {
            // Перенести существующие баллы в task_criteria до удаления
            // колонки — по одной записи на (category_id, number), берём
            // максимум среди заданий с этим номером на случай расхождений
            // (баллы должны совпадать, но подстрахуемся).
            $rows = DB::table('tasks')
                ->select('category_id', 'number', DB::raw('MAX(max_score) as max_score'))
                ->groupBy('category_id', 'number')
                ->get();

            foreach ($rows as $row) {
                $existing = DB::table('task_criteria')
                    ->where('category_id', $row->category_id)
                    ->where('number', $row->number)
                    ->first();

                if ($existing) {
                    DB::table('task_criteria')
                        ->where('id', $existing->id)
                        ->update(['max_score' => $row->max_score]);
                } else {
                    DB::table('task_criteria')->insert([
                        'category_id' => $row->category_id,
                        'number' => $row->number,
                        'max_score' => $row->max_score,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::statement('ALTER TABLE tasks DROP COLUMN max_score');
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('tasks', 'max_score')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->unsignedSmallInteger('max_score')->default(1)->after('hint');
            });
        }

        if (Schema::hasColumn('task_criteria', 'max_score')) {
            Schema::table('task_criteria', function (Blueprint $table) {
                $table->dropColumn('max_score');
            });
        }
    }
};
