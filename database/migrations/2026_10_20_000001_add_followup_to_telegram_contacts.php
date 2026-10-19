<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Второе касание от Александра (FunnelBot::sendFollowup): через сутки после
 * «Как вам урок?» или вопроса после оценки тем, кто досмотрел урок или
 * оценил хорошо, но так ничего и не написал. Одно на человека.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_contacts', function (Blueprint $table) {
            $table->timestamp('followup_due_at')->nullable()->index()->after('question_sent_at');
            $table->timestamp('followup_sent_at')->nullable()->after('followup_due_at');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_contacts', function (Blueprint $table) {
            $table->dropColumn(['followup_due_at', 'followup_sent_at']);
        });
    }
};
