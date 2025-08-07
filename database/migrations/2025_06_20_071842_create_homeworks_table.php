<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('homeworks', function (Blueprint $table) {
            $table->unsignedBigInteger('lesson_id')->nullable();  // Для связи с уроками
            $table->foreign('lesson_id')->references('id')->on('lessons')->onDelete('set null');  // Обновление внешнего ключа
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();     // Общая информация
            $table->enum('type', ['homework', 'mock']);  // Тип: обычная домашка или пробник
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('homeworks');
    }
};
