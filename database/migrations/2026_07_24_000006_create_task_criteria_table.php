<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Критерии проверки привязаны к номеру задания в экзамене (категория +
 * номер), а не к конкретному вопросу в банке — один и тот же набор
 * критериев применяется ко всем вопросам с этим номером (в 95% случаев,
 * по словам заказчика). Исключение — редкая точечная перезапись прямо на
 * Task.criteria_override (см. следующую миграцию), а не отдельный набор
 * критериев на каждый случай.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('task_criteria')) {
            return;
        }

        Schema::create('task_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('number')->nullable();
            $table->text('criteria')->nullable();
            $table->text('ai_rationale_template')->nullable();
            $table->text('comment')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();

            // Один набор критериев на пару (категория, номер) — не исключение,
            // а правило, см. комментарий выше.
            $table->unique(['category_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_criteria');
    }
};
