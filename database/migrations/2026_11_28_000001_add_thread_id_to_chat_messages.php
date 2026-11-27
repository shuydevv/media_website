<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ветки в чате трансляций: ответ на сообщение уходит в его ветку и не
 * засоряет общую ленту — ученики обсуждают, не мешая остальным.
 * thread_id — исходное сообщение ветки (у самого исходного — null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('thread_id')->nullable()->after('reply_to_id');
            $table->index(['lesson_id', 'thread_id']);
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropIndex(['lesson_id', 'thread_id']);
            $table->dropColumn('thread_id');
        });
    }
};
