<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Бот воронки пробного урока (App\Service\Telegram\FunnelBot).
 *
 * Bot API не отдаёт историю переписки — Telegram только присылает новые
 * апдейты на webhook. Поэтому вся переписка (входящие, автосообщения, ответы
 * админа) копится здесь, в telegram_messages, иначе её не будет нигде.
 *
 * Состояние воронки — отметками времени на контакте, а не отдельным
 * «статусом»: «можно ли ещё слать автосообщения» выводится из них на лету
 * (TelegramContact::automationAllowed()), так что отмена таймеров — это
 * просто проверка в момент отправки, без поиска и удаления задач в очереди.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('telegram_user_id')->unique();
            $table->string('username')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();

            // Метка из первого /start (vk_ad1) и урок, на который пришёл.
            // Повторный /start с другой меткой не перезаписывает — аналитика
            // считает по первому касанию.
            $table->string('source', 64)->nullable()->index();
            $table->string('lesson', 8);
            // Персональная ссылка /go/{token}/{урок} — по ней видно, открыл ли
            // человек урок (см. Telegram\LessonLinkController).
            $table->string('link_token', 24)->unique();

            // Тема в админ-группе и эмодзи-статус в её названии (чтобы не
            // переименовывать тему, когда статус не изменился).
            $table->unsignedBigInteger('topic_id')->nullable()->unique();
            $table->string('topic_status', 8)->nullable();

            $table->string('rating', 8)->nullable(); // up | down
            $table->timestamp('rated_at')->nullable();
            $table->unsignedBigInteger('rating_message_id')->nullable();

            $table->timestamp('opened_lesson_at')->nullable();
            $table->timestamp('opened_bonus_at')->nullable();

            $table->timestamp('reminder_due_at')->nullable()->index();
            $table->timestamp('reminder_sent_at')->nullable();

            $table->timestamp('first_user_message_at')->nullable();
            $table->timestamp('last_user_message_at')->nullable();
            $table->unsignedInteger('user_messages_count')->default(0);
            $table->timestamp('first_admin_message_at')->nullable();
            $table->timestamp('last_admin_message_at')->nullable();

            $table->timestamp('blocked_at')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('last_start_at');
            $table->timestamps();

            $table->index('started_at');
        });

        Schema::create('telegram_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telegram_contact_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 3); // in | out
            // user — пишет человек; auto — автосообщение воронки; admin —
            // ответ из темы админ-группы.
            $table->string('kind', 8);
            $table->string('step', 24)->nullable(); // ключ текста для auto
            $table->text('text')->nullable();
            $table->string('media_type', 16)->nullable();
            $table->string('file_id')->nullable();
            $table->unsignedBigInteger('telegram_message_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['telegram_contact_id', 'id']);
        });

        Schema::create('telegram_texts', function (Blueprint $table) {
            $table->string('key', 32)->primary();
            $table->text('text');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_texts');
        Schema::dropIfExists('telegram_messages');
        Schema::dropIfExists('telegram_contacts');
    }
};
