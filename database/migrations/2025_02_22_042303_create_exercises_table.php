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
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->text('title'); // в заголовке пишется само задание
            $table->string('ex_number'); // номер задания
            $table->text('content_options')->nullable(); // в контенте пишутся варианты ответа 
            $table->string('content_column_1_title')->nullable(); // 
            $table->text('content_column_1_content')->nullable(); // 
            $table->string('content_column_2_title')->nullable(); // 
            $table->text('content_column_2_content')->nullable(); // 
            $table->string('answer')->nullable(); // ответ
            $table->text('comment')->nullable(); // объяснение решения
            // $table->boolean('short_answer')->nullable();
            // существует ли короткий ответ? В заданиях второй части его нет
            $table->text('text_spoiler')->nullable();
            $table->string('main_image')->nullable();
            $table->unsignedBigInteger('topic_id')->nullable(); // foreign key

            $table->timestamps();

            $table->index('topic_id', 'exercise_topic_idx');
            $table->foreign('topic_id', 'exercise_topic_fk')->on('topics')->references('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};
