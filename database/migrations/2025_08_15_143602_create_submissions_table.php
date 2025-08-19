<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();

            // ВАЖНО: правильные FK — на homeworks и users
            $table->foreignId('homework_id')
                ->constrained('homeworks')     // НЕ 'homework'
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Номер попытки
            $table->unsignedTinyInteger('attempt_no')->default(1);

            // Ответы и статусы
            $table->json('answers')->nullable();
            $table->string('status')->default('pending'); // pending|checked|expired

            // Баллы и подробности автопроверки (оставь, если нужно сразу)
            $table->unsignedSmallInteger('autocheck_score')->nullable();
            $table->unsignedSmallInteger('manual_score')->nullable();
            $table->unsignedSmallInteger('total_score')->nullable();
            $table->json('per_task_results')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
