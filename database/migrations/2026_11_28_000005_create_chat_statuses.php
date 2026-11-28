<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Статус ученика в чате трансляции (плашка у имени, как у преподавателя).
// lesson_id — только на этот урок; null — навсегда. У ученика один статус.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_statuses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->unsignedBigInteger('lesson_id')->nullable();
            $table->string('text', 24);
            $table->string('color', 12);
            $table->unsignedBigInteger('set_by');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_statuses');
    }
};
