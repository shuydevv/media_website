<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Точечное открытие доступа к конкретной домашке конкретному ученику в обход
 * Homework::isLessonBeforeEnrollment() — например, ученик записался на курс
 * позже, чем прошёл урок, к которому привязана домашка, и без такого
 * исключения никогда не увидит и не сможет открыть её (см. Homework::
 * isUnlockedFor(), вызывается из isLessonBeforeEnrollment()). Не имеет
 * отношения к isLessonUpcoming() — урок, который ещё не наступил, этим не
 * открывается, там просто нечего показывать.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homework_unlocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('homework_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('granted_by')->nullable();
            $table->foreign('homework_id')->references('id')->on('homeworks')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('granted_by')->references('id')->on('users')->onDelete('set null');
            $table->unique(['homework_id', 'user_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homework_unlocks');
    }
};
