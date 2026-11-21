<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * telegram_message_id — уведомление о сообщении в теме «🛒 Авито». Реплай
 * админа на это уведомление уходит клиенту в тот же чат Авито (ReplyToAvito).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('avito_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('telegram_message_id')->nullable()->after('text');
            $table->index('telegram_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('avito_messages', function (Blueprint $table) {
            $table->dropIndex(['telegram_message_id']);
            $table->dropColumn('telegram_message_id');
        });
    }
};
