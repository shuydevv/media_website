<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('homework_tasks', function (Blueprint $table) {
            // В SQLite json => text, поэтому дубликаты легко ловятся

            if (!Schema::hasColumn('homework_tasks', 'passage_text')) {
                $table->longText('passage_text')->nullable()->after('question_text');
            }

            if (!Schema::hasColumn('homework_tasks', 'media_path')) {
                $table->string('media_path')->nullable()->after('passage_text');
            }

            if (!Schema::hasColumn('homework_tasks', 'options')) {
                $table->json('options')->nullable()->after('media_path');
            }

            if (!Schema::hasColumn('homework_tasks', 'left_title')) {
                $table->string('left_title')->nullable()->after('options');
            }

            if (!Schema::hasColumn('homework_tasks', 'right_title')) {
                $table->string('right_title')->nullable()->after('left_title');
            }

            if (!Schema::hasColumn('homework_tasks', 'order_matters')) {
                $table->boolean('order_matters')->default(false)->after('right_title');
            }

            if (!Schema::hasColumn('homework_tasks', 'table_content')) {
                $table->json('table_content')->nullable()->after('order_matters');
            }
        });
    }

    public function down(): void
    {
        Schema::table('homework_tasks', function (Blueprint $table) {
            // Удаляем только существующие — чтобы откат тоже был безопасным
            foreach ([
                'table_content','order_matters','right_title','left_title',
                'options','media_path','passage_text'
            ] as $col) {
                if (Schema::hasColumn('homework_tasks', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
