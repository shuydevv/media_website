<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Черновики ИИ-проверки для ответов из тренажёра «Задания» — как
 * submissions.ai_drafts: task_id → черновик (или отметка «выгружено»).
 * Заполняет DesktopGradingExchange при загрузке результата из Claude.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_reviews', function (Blueprint $table) {
            $table->json('ai_drafts')->nullable()->after('comment');
        });
    }

    public function down(): void
    {
        Schema::table('task_reviews', function (Blueprint $table) {
            $table->dropColumn('ai_drafts');
        });
    }
};
