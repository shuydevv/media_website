<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHomeworkTasksTable extends Migration
{
    public function up(): void
    {
        Schema::create('homework_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('homework_id')->constrained('homeworks')->onDelete('cascade');

            $table->string('type'); // Тип задания: multiple_choice, matching, written и т.д.
            $table->text('question_text')->nullable(); // Текст вопроса/задания

            $table->json('options')->nullable(); // Варианты (для multiple_choice)
            $table->json('matches')->nullable(); // Соотнесения (для matching)
            $table->json('table')->nullable();   // Таблица (3x4 ячейки, для типа table)

            $table->string('answer'); // Правильный ответ (универсальное поле)

            $table->string('image_path')->nullable(); // Путь к изображению (если есть)

            $table->string('task_number')->nullable(); // Номер задания в пробнике
            $table->unsignedInteger('order')->nullable(); // Порядковый номер в списке

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homework_tasks');
    }
}
