<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Самостоятельное прорешивание задания из банка в личном кабинете — один
 * вопрос за раз, не многошаговый wizard с finish/attempt_no/status, поэтому
 * не переиспользуем тяжёлый Submission — отдельная лёгкая таблица.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('task_attempts')) {
            return;
        }

        Schema::create('task_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('answer')->nullable();
            $table->string('status')->nullable(); // ok|partial|fail — только для авто-типов
            $table->unsignedSmallInteger('score')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_attempts');
    }
};
