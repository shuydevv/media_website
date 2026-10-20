<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Пачки ИИ-проверки письменных заданий. Канал:
 * - desktop — выгрузка файла для проверки в Claude Desktop и загрузка
 *   результата обратно (DesktopGradingExchange), external_id — id выгрузки
 *   из файла;
 * - api — Message Batch в Claude API (ClaudeBatchGrader), external_id — id
 *   пачки у Anthropic.
 * Сами черновики в обоих случаях ложатся в submissions.ai_drafts — здесь
 * только то, что нужно для сверки и статуса на странице очереди.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_grading_batches', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 16)->default('desktop'); // desktop | api
            $table->string('external_id')->unique();
            $table->string('status', 16)->default('in_progress'); // in_progress | ended
            $table->unsignedInteger('request_count')->default(0);
            $table->unsignedInteger('succeeded_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            // desktop: id заданий выгрузки («s{submission}-t{task}») — чтобы при
            // загрузке результата увидеть, какие задания не вернулись.
            $table->json('items')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['channel', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_grading_batches');
    }
};
