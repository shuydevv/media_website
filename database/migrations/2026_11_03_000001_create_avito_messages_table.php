<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * avito_messages — входящие сообщения Авито (webhook мессенджера). Как
 * site_leads: сначала сюда, потом в тему «🛒 Авито» задачей очереди
 * (sent_at = null — ещё не дошло). Уникальный message_id — защита от
 * повторов webhook-а; chat_id и user_id пригодятся для ответов из Telegram.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avito_messages', function (Blueprint $table) {
            $table->id();
            $table->string('message_id', 64)->unique();
            $table->string('chat_id', 64);
            // наш аккаунт на Авито и автор сообщения
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('author_id');
            $table->unsignedBigInteger('item_id')->nullable();
            // text, image, voice, link, item, location, call, file, system…
            $table->string('type', 20);
            $table->text('text')->nullable();
            $table->timestamp('sent_at_avito')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('chat_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avito_messages');
    }
};
