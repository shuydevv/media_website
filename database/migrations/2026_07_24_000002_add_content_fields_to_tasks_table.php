<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task расширяется от «банка критериев проверки» до полноценного банка
 * заданий: те же поля содержания, что уже есть у HomeworkTask (question_text,
 * options, matches, table_content, image_path, answer, hint, max_score,
 * order_matters, image_auto_options, left_title, right_title) — задание в
 * банке теперь самодостаточно, не обязано жить внутри домашки.
 *
 * is_public — управляет показом на публичной SEO-странице (см.
 * HomeworkController-аналог для Exercise); по умолчанию выключено, админ
 * включает осознанно.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tasks')) {
            return;
        }

        Schema::table('tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('tasks', 'type')) {
                $table->string('type')->nullable()->after('number');
            }
            if (!Schema::hasColumn('tasks', 'question_text')) {
                $table->text('question_text')->nullable()->after('type');
            }
            if (!Schema::hasColumn('tasks', 'passage_text')) {
                $table->text('passage_text')->nullable()->after('question_text');
            }
            if (!Schema::hasColumn('tasks', 'options')) {
                $table->json('options')->nullable()->after('passage_text');
            }
            if (!Schema::hasColumn('tasks', 'matches')) {
                $table->json('matches')->nullable()->after('options');
            }
            if (!Schema::hasColumn('tasks', 'table_content')) {
                $table->json('table_content')->nullable()->after('matches');
            }
            if (!Schema::hasColumn('tasks', 'image_path')) {
                $table->string('image_path')->nullable()->after('table_content');
            }
            if (!Schema::hasColumn('tasks', 'image_auto_options')) {
                $table->json('image_auto_options')->nullable()->after('image_path');
            }
            if (!Schema::hasColumn('tasks', 'order_matters')) {
                $table->boolean('order_matters')->default(false)->after('image_auto_options');
            }
            if (!Schema::hasColumn('tasks', 'left_title')) {
                $table->string('left_title')->nullable()->after('order_matters');
            }
            if (!Schema::hasColumn('tasks', 'right_title')) {
                $table->string('right_title')->nullable()->after('left_title');
            }
            if (!Schema::hasColumn('tasks', 'answer')) {
                $table->text('answer')->nullable()->after('right_title');
            }
            if (!Schema::hasColumn('tasks', 'hint')) {
                $table->text('hint')->nullable()->after('answer');
            }
            if (!Schema::hasColumn('tasks', 'max_score')) {
                $table->unsignedSmallInteger('max_score')->default(1)->after('hint');
            }
            if (!Schema::hasColumn('tasks', 'is_public')) {
                $table->boolean('is_public')->default(false)->after('max_score');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('tasks')) {
            return;
        }

        Schema::table('tasks', function (Blueprint $table) {
            foreach ([
                'type', 'question_text', 'passage_text', 'options', 'matches',
                'table_content', 'image_path', 'image_auto_options', 'order_matters',
                'left_title', 'right_title', 'answer', 'hint', 'max_score', 'is_public',
            ] as $column) {
                if (Schema::hasColumn('tasks', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
