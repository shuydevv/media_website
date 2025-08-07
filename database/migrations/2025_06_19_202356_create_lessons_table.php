<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();

            $table->foreignId('course_session_id')->constrained()->onDelete('cascade');

            $table->string('title')->nullable(); // Название (тема) урока
            $table->string('description')->nullable(); // Название (тема) урока
            $table->string('meet_link')->nullable(); // Ссылка на трансляцию
            $table->string('recording_link')->nullable(); // Ссылка на запись
            $table->string('notes_link')->nullable(); // Ссылка на конспект
            $table->foreignId('homework_id')->nullable()->constrained()->nullOnDelete(); // Домашнее задание
            $table->string('image')->nullable(); // Обложка

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
