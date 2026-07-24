<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tasks', 'criteria')) {
            // Перенести уже существующие критерии в task_criteria до того, как
            // колонки будут удалены с tasks — по одной записи на (category_id, number).
            $rows = DB::table('tasks')->get(['category_id', 'number', 'criteria', 'ai_rationale_template', 'comment', 'created_by', 'updated_by']);

            foreach ($rows as $row) {
                $hasCriteriaData = filled($row->criteria) || filled($row->ai_rationale_template) || filled($row->comment);
                if (!$hasCriteriaData) {
                    continue;
                }

                $exists = DB::table('task_criteria')
                    ->where('category_id', $row->category_id)
                    ->where('number', $row->number)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('task_criteria')->insert([
                    'category_id' => $row->category_id,
                    'number' => $row->number,
                    'criteria' => $row->criteria,
                    'ai_rationale_template' => $row->ai_rationale_template,
                    'comment' => $row->comment,
                    'created_by' => $row->created_by,
                    'updated_by' => $row->updated_by,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if (!Schema::hasColumn('tasks', 'criteria_override')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->text('criteria_override')->nullable()->after('max_score');
            });
        }

        foreach (['criteria', 'ai_rationale_template', 'comment', 'created_by', 'updated_by'] as $column) {
            if (Schema::hasColumn('tasks', $column)) {
                DB::statement("ALTER TABLE tasks DROP COLUMN {$column}");
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('tasks', 'criteria')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->text('criteria')->nullable();
                $table->text('ai_rationale_template')->nullable();
                $table->text('comment')->nullable();
                $table->foreignId('created_by')->nullable();
                $table->foreignId('updated_by')->nullable();
            });
        }

        if (Schema::hasColumn('tasks', 'criteria_override')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropColumn('criteria_override');
            });
        }
    }
};
