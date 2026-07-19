<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_user', function (Blueprint $table) {
            $table->unsignedSmallInteger('billing_interval_days')->nullable()->after('promo_code');
            $table->timestamp('next_payment_due_at')->nullable()->after('billing_interval_days');
            $table->timestamp('promised_payment_expires_at')->nullable()->after('next_payment_due_at');
            $table->timestamp('promised_payment_used_at')->nullable()->after('promised_payment_expires_at');
            $table->timestamp('reminder_sent_at')->nullable()->after('promised_payment_used_at');

            $table->index('next_payment_due_at');
        });
    }

    public function down(): void
    {
        Schema::table('course_user', function (Blueprint $table) {
            $table->dropIndex(['next_payment_due_at']);
            $table->dropColumn([
                'billing_interval_days',
                'next_payment_due_at',
                'promised_payment_expires_at',
                'promised_payment_used_at',
                'reminder_sent_at',
            ]);
        });
    }
};
