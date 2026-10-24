<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Диагностика по обществознанию (ТЗ — docs/diagnostic/, раздел 11).
 *
 * diagnostic_attempts — одно прохождение. Контакт бота diag (telegram_contacts)
 * может быть пустым: анонимное прохождение (allow_anonymous, разработка) —
 * его узнают по token в cookie.
 *
 * diagnostic_answers — каждый ответ, с первого дня: через 200–300
 * прохождений по ним калибруется реальная сложность вопросов. Вопрос — id
 * из банка (JSON) + хеш содержимого: правка текста не смешивает статистику.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnostic_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('token', 32)->unique();
            $table->foreignId('telegram_contact_id')->nullable()->constrained()->nullOnDelete();

            $table->string('status', 12)->default('active'); // active | finished
            $table->string('entry', 12)->nullable(); // miniapp | link | anonymous
            $table->string('platform', 24)->nullable(); // Telegram.WebApp.platform

            $table->string('grade', 8)->nullable(); // config diagnostic.grades
            $table->unsignedTinyInteger('target_score')->nullable();
            $table->timestamp('target_set_at')->nullable(); // задан, даже если «не знаю» (target_score пуст)

            $table->json('block_order'); // ключи блоков в порядке прохождения
            $table->unsignedTinyInteger('block_index')->default(0);
            $table->string('current_question_id', 64)->nullable();
            $table->unsignedTinyInteger('streak')->default(0);

            // Итоги блоков (BlockRun) и прогноз (ScoreModel) — после последнего блока.
            $table->json('levels')->nullable();
            $table->json('levels_low')->nullable();
            $table->json('result')->nullable();

            $table->string('improve', 4)->nullable(); // yes | no — «Хочешь улучшить свои знания?»
            $table->string('improve_source', 4)->nullable(); // page | bot
            $table->timestamp('improve_at')->nullable();
            // Итог в чате бота с кнопками «Да, хочу» / «Пока нет»: ответили на
            // странице — кнопки под ним убираем (DiagBot).
            $table->unsignedBigInteger('bot_message_id')->nullable();

            $table->timestamp('started_at');
            $table->timestamp('last_answer_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['telegram_contact_id', 'status']);
            $table->index('finished_at');
        });

        Schema::create('diagnostic_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diagnostic_attempt_id')->constrained()->cascadeOnDelete();
            $table->string('block', 16);
            $table->unsignedTinyInteger('level');
            $table->unsignedTinyInteger('position'); // номер вопроса в блоке, с 1
            $table->string('question_id', 64)->index();
            $table->string('question_hash', 12);
            $table->string('mechanic', 12);
            $table->json('answer');
            $table->boolean('correct');
            $table->boolean('guess')->default(false);
            $table->unsignedInteger('answer_ms')->nullable(); // от показа вопроса до ответа
            $table->unsignedInteger('explain_ms')->nullable(); // сколько читал пояснение
            $table->timestamp('created_at')->nullable();

            $table->index(['diagnostic_attempt_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnostic_answers');
        Schema::dropIfExists('diagnostic_attempts');
    }
};
