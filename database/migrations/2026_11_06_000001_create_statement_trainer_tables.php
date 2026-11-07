<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Тренажёр «Верные суждения» (docs/statements/Верные суждения — механика.md).
     * Таблицы явно InnoDB: ответ и корм пишутся под lockForUpdate() в транзакции.
     */
    public function up(): void
    {
        // База суждений — заливается из docs/statements/statements.json
        // (php artisan statements:import), руками не правится.
        Schema::create('statements', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            // Ключ перезаливки — id из JSON («eh-5841-1»).
            $table->string('key', 60)->unique();
            $table->string('block', 16);
            // «о социальной стратификации» — подпись карточки. Длины явные:
            // по умолчанию строки в проекте — 191 символ, а темы ege-hub
            // доходят до 225.
            $table->string('about', 500)->nullable();
            $table->text('text');
            $table->boolean('is_true');
            $table->text('explanation')->nullable();
            // «Как было бы верно» — для неверных суждений, пока почти пусто.
            $table->text('correction')->nullable();
            // active | no_explanation | check | conflict | removed
            $table->string('status', 16);
            $table->string('issue', 500)->nullable();
            // Ученику выдаются только активные (status = active).
            $table->boolean('is_active')->default(false);
            $table->unsignedInteger('source_task_id')->nullable();
            $table->unsignedTinyInteger('source_line')->nullable();
            $table->string('theme', 500)->nullable();
            $table->char('hash', 40);
            $table->timestamps();

            $table->index(['is_active', 'block']);
        });

        // Прогресс ученика по суждению. due_on — когда вернуть на повтор
        // (null — повторять не нужно: ответил верно с первого раза или
        // прошёл всю лесенку повторов).
        Schema::create('user_statements', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('statement_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('step')->default(0);
            $table->date('due_on')->nullable();
            $table->unsignedSmallInteger('right_count')->default(0);
            $table->unsignedSmallInteger('wrong_count')->default(0);
            $table->boolean('last_correct')->default(false);
            $table->timestamp('last_answered_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'statement_id']);
            $table->index(['user_id', 'due_on']);
        });

        Schema::create('statement_sessions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // regular — обычная сессия; mistakes — «Повторить ошибки» сразу
            // после сессии: расписание не трогает, корма не даёт.
            $table->string('kind', 12)->default('regular');
            $table->json('blocks');
            // Карточки по порядку: [{id, who}] — who — персонаж.
            $table->json('items');
            // Ответы по позициям: [{answer, correct}].
            $table->json('results')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->unsignedSmallInteger('total');
            $table->unsignedSmallInteger('correct')->default(0);
            $table->unsignedSmallInteger('streak')->default(0);
            $table->unsignedSmallInteger('best_streak')->default(0);
            // in_progress | finished | abandoned
            $table->string('status', 12)->default('in_progress');
            $table->date('day');
            $table->unsignedTinyInteger('corm_awarded')->default(0);
            $table->foreignId('parent_id')->nullable()->constrained('statement_sessions')->nullOnDelete();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'day']);
        });

        // Каждый ответ — для аналитики (настоящая сложность, частые ошибки).
        Schema::create('statement_answers', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('session_id')->constrained('statement_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('statement_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            // true | false | unknown
            $table->string('answer', 8);
            $table->boolean('correct');
            $table->string('who', 12)->nullable();
            $table->unsignedInteger('ms')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['session_id', 'position']);
            $table->index('statement_id');
        });

        // «Ошибка в карточке?» от учеников.
        Schema::create('statement_reports', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('statement_id')->constrained()->cascadeOnDelete();
            $table->string('message', 1000)->nullable();
            // new | done
            $table->string('status', 8)->default('new');
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statement_reports');
        Schema::dropIfExists('statement_answers');
        Schema::dropIfExists('statement_sessions');
        Schema::dropIfExists('user_statements');
        Schema::dropIfExists('statements');
    }
};
