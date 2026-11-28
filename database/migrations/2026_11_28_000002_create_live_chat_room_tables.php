<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Чат трансляций, шаг 2: пульт преподавателя (перерыв, тишина, «Пон / Не
 * пон», онлайн), эмоции учеников, гости по ссылке.
 *
 * Гость — не пользователь: в users он не попадает (иначе — в CRM и
 * аналитику). В чате у него условный id = LiveChatService::GUEST_BASE +
 * chat_guests.id, поэтому сообщения, реакции и голоса гостя лежат в тех же
 * колонках user_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_chats', function (Blueprint $table) {
            $table->timestamp('break_until')->nullable();
            $table->timestamp('quiet_until')->nullable();
            $table->string('guest_token', 40)->nullable()->unique();
        });

        Schema::create('chat_guests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lesson_id')->index();
            $table->string('name', 64)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });

        // Кто сейчас в чате — отметка раз в ~20 с от каждой открытой страницы.
        Schema::create('chat_presence', function (Blueprint $table) {
            $table->unsignedBigInteger('lesson_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('seen_at');
            $table->primary(['lesson_id', 'user_id']);
            $table->index(['lesson_id', 'seen_at']);
        });

        Schema::create('chat_polls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lesson_id')->index();
            $table->unsignedBigInteger('created_by');
            $table->timestamp('ends_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('chat_poll_votes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('poll_id');
            $table->unsignedBigInteger('user_id');
            $table->string('voter_name', 64);
            $table->boolean('choice'); // true — «пон», false — «не пон»
            $table->timestamp('created_at')->nullable();
            $table->unique(['poll_id', 'user_id']);
        });

        Schema::create('chat_emotions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lesson_id');
            $table->unsignedBigInteger('user_id');
            $table->string('author_name', 64);
            $table->string('code', 24);
            $table->timestamp('ends_at');
            $table->timestamps();
            $table->index(['lesson_id', 'ends_at']);
            $table->index(['lesson_id', 'user_id']);
        });

        Schema::create('chat_emotion_supports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('emotion_id');
            $table->unsignedBigInteger('user_id');
            $table->string('supporter_name', 64);
            $table->timestamp('created_at')->nullable();
            $table->unique(['emotion_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_emotion_supports');
        Schema::dropIfExists('chat_emotions');
        Schema::dropIfExists('chat_poll_votes');
        Schema::dropIfExists('chat_polls');
        Schema::dropIfExists('chat_presence');
        Schema::dropIfExists('chat_guests');
        Schema::table('lesson_chats', function (Blueprint $table) {
            $table->dropUnique(['guest_token']);
            $table->dropColumn(['break_until', 'quiet_until', 'guest_token']);
        });
    }
};
