<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();

            // Категория (предмет) — FK на categories.id
            $table->unsignedBigInteger('category_id');  // обязательная
            $table->string('number')->nullable();       // номер задания (опционально)

            $table->text('criteria');                   // критерии (машиночитаемо)
            $table->text('ai_rationale_template')->nullable(); // шаблон "Обоснование баллов"
            $table->text('comment')->nullable();        // рекомендации для проверяющего

            // Служебное
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            // Индексы и внешние ключи
            $table->index(['category_id']);
            $table->index(['number']);

            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
