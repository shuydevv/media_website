<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Рассылки из темы «📣 Рассылка» админ-группы (App\Service\Telegram\Broadcast\*).
 *
 * telegram_broadcasts — одна рассылка: какие сообщения группы копировать
 * (source_message_ids — альбом это несколько сообщений), кому, карточка
 * черновика в теме и её состояние. Получатели черновика не хранятся —
 * считаются заново (BroadcastAudience), в момент старта фиксируются в
 * telegram_broadcast_recipients: дальше это и очередь отправки, и итог.
 *
 * excluded_user_ids — исключённые только из этой рассылки, по
 * telegram_user_id: человек, а не контакт в конкретном боте.
 *
 * telegram_contacts.broadcast_opt_out_at — исключён из всех рассылок;
 * last_broadcast_at — когда ему последний раз ушла рассылка (для 📣 в
 * названии темы, если он на неё ответил).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->string('status', 16)->default('draft');
            $table->string('audience', 8)->default('all');
            $table->bigInteger('source_chat_id');
            $table->json('source_message_ids');
            $table->string('media_group_id', 64)->nullable()->index();
            $table->text('text')->nullable();
            $table->string('media_type', 16)->nullable();
            $table->unsignedSmallInteger('media_count')->default(0);
            $table->json('excluded_user_ids')->nullable();
            $table->bigInteger('card_message_id')->nullable()->index();
            $table->string('card_state', 16)->default('main');
            $table->timestamp('pending_at')->nullable();
            $table->timestamp('card_refreshed_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('progress_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('stop_reason')->nullable();
            $table->bigInteger('author_telegram_id')->nullable();
            $table->string('author_name')->nullable();
            $table->timestamps();
        });

        Schema::create('telegram_broadcast_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telegram_broadcast_id')->constrained()->cascadeOnDelete();
            $table->foreignId('telegram_contact_id')->constrained()->cascadeOnDelete();
            $table->string('status', 8)->default('pending');
            $table->bigInteger('telegram_message_id')->nullable();
            $table->string('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('replied_at')->nullable();

            $table->unique(['telegram_broadcast_id', 'telegram_contact_id'], 'tg_broadcast_recipient_unique');
            $table->index(['telegram_broadcast_id', 'status'], 'tg_broadcast_recipient_status');
            $table->index(['telegram_contact_id', 'sent_at'], 'tg_broadcast_recipient_contact');
        });

        Schema::table('telegram_contacts', function (Blueprint $table) {
            $table->timestamp('broadcast_opt_out_at')->nullable();
            $table->timestamp('last_broadcast_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('telegram_contacts', function (Blueprint $table) {
            $table->dropColumn(['broadcast_opt_out_at', 'last_broadcast_at']);
        });
        Schema::dropIfExists('telegram_broadcast_recipients');
        Schema::dropIfExists('telegram_broadcasts');
    }
};
