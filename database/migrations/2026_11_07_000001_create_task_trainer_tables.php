<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Станция «Задания» (docs/bank/Задания — механика.md): поля банка для
     * импорта из ege-hub, раунды первой части, прогресс по заданиям,
     * подходы второй части (самопроверка и отправка куратору).
     * Таблицы явно InnoDB: ответы и корм пишутся под lockForUpdate().
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // egehub — залито bank:import; null — задание, созданное в админке.
            $table->string('source', 16)->nullable()->after('id');
            // id задания в выгрузке — ключ перезаливки.
            $table->unsignedInteger('external_id')->nullable()->unique()->after('source');
            // society | economy | social | politics | law — как в «Верных суждениях».
            $table->string('block', 16)->nullable()->after('topic_id');
            // Темы ege-hub доходят до 225 символов (длина по умолчанию — 191).
            $table->string('theme', 500)->nullable()->after('block');
            // Общий текст 17 и 18 — решаются одним подходом.
            $table->string('group_key', 16)->nullable()->index()->after('theme');
            // active | hidden. Станция выдаёт только active с заполненным block.
            $table->string('status', 16)->default('active')->after('group_key');
            $table->string('issue', 500)->nullable()->after('status');
            // id одинаковых заданий выгрузки, склеенных в это.
            $table->json('also')->nullable()->after('issue');
            // Отпечаток содержания на момент импорта: не совпадает с текущим —
            // автор правил задание в админке, перезаливка его не трогает.
            $table->char('import_hash', 40)->nullable()->after('also');

            $table->index(['status', 'number', 'block']);
        });

        // Раунд первой части: 10 заданий выбранных номеров/блоков.
        Schema::create('task_rounds', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // regular | mistakes («Повторить ошибки» — без корма и расписания).
            $table->string('kind', 12)->default('regular');
            $table->json('numbers');
            $table->json('blocks');
            // id заданий по порядку.
            $table->json('items');
            // Ответы по позициям: [{answer, score, max}].
            $table->json('results')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->unsignedSmallInteger('total');
            $table->unsignedSmallInteger('score')->default(0);
            $table->unsignedSmallInteger('max_score')->default(0);
            // in_progress | finished | abandoned
            $table->string('status', 12)->default('in_progress');
            $table->date('day');
            $table->unsignedTinyInteger('corm_awarded')->default(0);
            $table->foreignId('parent_id')->nullable()->constrained('task_rounds')->nullOnDelete();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'day']);
        });

        // Прогресс ученика по заданию первой части (повтор ошибок).
        Schema::create('user_tasks', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('step')->default(0);
            $table->date('due_on')->nullable();
            $table->unsignedSmallInteger('right_count')->default(0);
            $table->unsignedSmallInteger('wrong_count')->default(0);
            $table->boolean('last_correct')->default(false);
            $table->timestamp('last_answered_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'task_id']);
            $table->index(['user_id', 'due_on']);
        });

        // Попытки банка (task_attempts) — плюс раунд и время на ответ.
        Schema::table('task_attempts', function (Blueprint $table) {
            $table->foreignId('round_id')->nullable()->after('user_id')->constrained('task_rounds')->nullOnDelete();
            $table->unsignedSmallInteger('position')->nullable()->after('round_id');
            $table->unsignedInteger('ms')->nullable()->after('score');
        });

        // Подход второй части: одно задание (или 17+18 с общим текстом).
        Schema::create('task_reviews', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Первое задание подхода; все задания — в items.
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->json('items');
            // Ответы ученика: {task_id: текст}. Черновик пишется сюда же.
            $table->json('answers')->nullable();
            // draft — пишет; self — сверился с образцом; sent — ждёт
            // куратора; checked — проверено.
            $table->string('status', 12)->default('draft');
            // Самопроверка: {task_id: {элемент: балл}} и итог.
            $table->json('self_marks')->nullable();
            $table->unsignedTinyInteger('self_score')->nullable();
            $table->unsignedTinyInteger('max_score');
            $table->timestamp('sent_at')->nullable();
            // Проверка куратора: {task_id: балл}, итог, комментарий.
            $table->json('scores')->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->text('comment')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            // Ученик открыл проверенный ответ.
            $table->timestamp('seen_at')->nullable();
            // Лок на время проверки — как у домашек (submissions).
            $table->unsignedBigInteger('locked_by')->nullable()->index();
            $table->timestamp('lock_expires_at')->nullable();
            $table->unsignedTinyInteger('corm_awarded')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'sent_at']);
        });

        // «Ошибка в задании?» от учеников.
        Schema::create('task_reports', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('message', 1000)->nullable();
            $table->string('status', 8)->default('new');
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_reports');
        Schema::dropIfExists('task_reviews');
        Schema::table('task_attempts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('round_id');
            $table->dropColumn(['position', 'ms']);
        });
        Schema::dropIfExists('user_tasks');
        Schema::dropIfExists('task_rounds');
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['status', 'number', 'block']);
            $table->dropUnique(['external_id']);
            $table->dropIndex(['group_key']);
            $table->dropColumn(['source', 'external_id', 'block', 'theme', 'group_key', 'status', 'issue', 'also', 'import_hash']);
        });
    }
};
