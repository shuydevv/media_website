<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * К одному уроку — не больше одной домашки (любого типа, homework или mock).
 * lesson_id остаётся nullable — домашек без урока может быть сколько угодно,
 * MySQL не считает несколько NULL нарушением уникальности.
 *
 * Это и есть настоящая защита от гонки в Admin\Homework\ImportController::
 * store(): чек "такая домашка уже есть?" перед созданием не атомарен сам по
 * себе — два почти одновременных запроса (двойной клик, повторная отправка
 * формы) оба успевают пройти проверку до того, как первый закоммитит запись,
 * и создают два дубля. Уникальный индекс — единственный способ гарантировать
 * это на уровне БД, независимо от таймингов запросов.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homeworks', function (Blueprint $table) {
            $table->unique('lesson_id', 'homeworks_lesson_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('homeworks', function (Blueprint $table) {
            $table->dropUnique('homeworks_lesson_id_unique');
        });
    }
};
