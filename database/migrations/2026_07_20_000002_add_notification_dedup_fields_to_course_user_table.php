<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_user', function (Blueprint $table) {
            $table->timestamp('overdue_notified_at')->nullable()->after('reminder_sent_at');
            $table->timestamp('promise_expiring_notified_at')->nullable()->after('overdue_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('course_user', function (Blueprint $table) {
            $table->dropColumn(['overdue_notified_at', 'promise_expiring_notified_at']);
        });
    }
};
