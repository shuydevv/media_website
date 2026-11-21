<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * avito_chats — чат Авито ↔ своя тема в админ-группе (как у людей из бота):
 * переписка с клиентом в одном месте, отвечать можно просто сообщением в теме.
 *
 * avito_messages.direction — out: наш ответ, отправленный в самом Авито
 * (с телефона/сайта), — показывается в теме без звука. from_bot — ответ,
 * ушедший из Telegram (ReplyToAvito): его эхо от webhook-а в тему не дублируем.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avito_chats', function (Blueprint $table) {
            $table->id();
            $table->string('chat_id', 64)->unique();
            $table->unsignedBigInteger('user_id'); // наш аккаунт на Авито
            $table->string('client_name')->nullable();
            $table->unsignedBigInteger('topic_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::table('avito_messages', function (Blueprint $table) {
            $table->string('direction', 3)->default('in')->after('type');
            $table->boolean('from_bot')->default(false)->after('direction');
        });
    }

    public function down(): void
    {
        Schema::table('avito_messages', function (Blueprint $table) {
            $table->dropColumn(['direction', 'from_bot']);
        });
        Schema::dropIfExists('avito_chats');
    }
};
