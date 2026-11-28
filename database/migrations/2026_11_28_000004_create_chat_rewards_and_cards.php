<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Корм за верный ответ в чате (chat_rewards: одно сообщение — одна награда),
// итоги урока сообщением-карточкой (chat_messages.card) и пик зрителей для итогов.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_rewards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lesson_id');
            $table->unsignedBigInteger('message_id')->unique();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('given_by');
            $table->string('giver_name', 64);
            $table->unsignedSmallInteger('corm');
            $table->timestamps();
            $table->index(['lesson_id', 'user_id']);
        });
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->json('card')->nullable()->after('body');
        });
        Schema::table('lesson_chats', function (Blueprint $table) {
            $table->unsignedInteger('peak_online')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_rewards');
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn('card');
        });
        Schema::table('lesson_chats', function (Blueprint $table) {
            $table->dropColumn('peak_online');
        });
    }
};
