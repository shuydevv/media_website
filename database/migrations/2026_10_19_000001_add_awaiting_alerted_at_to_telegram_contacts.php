<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Когда админу последний раз напомнили, что человек ждёт ответа
 * (telegram:remind-unanswered): одно напоминание на каждое его сообщение,
 * оставшееся без ответа, — не каждые 10 минут.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_contacts', function (Blueprint $table) {
            $table->timestamp('awaiting_alerted_at')->nullable()->after('last_admin_message_at');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_contacts', function (Blueprint $table) {
            $table->dropColumn('awaiting_alerted_at');
        });
    }
};
