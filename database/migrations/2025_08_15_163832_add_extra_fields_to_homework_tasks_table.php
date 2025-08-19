<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('homework_tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('homework_tasks', 'passage_text')) {
                $table->text('passage_text')->nullable()->after('question_text');
            }

            if (!Schema::hasColumn('homework_tasks', 'left_title')) {
                $table->string('left_title')->nullable()->after('passage_text');
            }

            if (!Schema::hasColumn('homework_tasks', 'right_title')) {
                $table->string('right_title')->nullable()->after('left_title');
            }

            if (!Schema::hasColumn('homework_tasks', 'image_auto_options')) {
                $table->json('image_auto_options')->nullable()->after('right_title');
            }

            if (!Schema::hasColumn('homework_tasks', 'image_auto_strict')) {
                $table->boolean('image_auto_strict')->default(false)->after('image_auto_options');
            }
        });
    }

    public function down(): void
    {
        Schema::table('homework_tasks', function (Blueprint $table) {
            if (Schema::hasColumn('homework_tasks', 'passage_text')) {
                $table->dropColumn('passage_text');
            }
            if (Schema::hasColumn('homework_tasks', 'left_title')) {
                $table->dropColumn('left_title');
            }
            if (Schema::hasColumn('homework_tasks', 'right_title')) {
                $table->dropColumn('right_title');
            }
            if (Schema::hasColumn('homework_tasks', 'image_auto_options')) {
                $table->dropColumn('image_auto_options');
            }
            if (Schema::hasColumn('homework_tasks', 'image_auto_strict')) {
                $table->dropColumn('image_auto_strict');
            }
        });
    }
};
