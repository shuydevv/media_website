<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Галочки «Напомнил»/«Оплатил» в карточке ученика /admin/crm.
     *
     * admin_reminded_at — пометка только для админа («я написал ученику
     * про оплату»), ученику ничего не шлёт. Отдельная колонка, а не
     * reminder_sent_at: тот — флаг автоматического напоминания
     * (SendBillingReminders), и ручная пометка там отключила бы письмо.
     *
     * payments.rollback_state — состояние записи course_user до платежа,
     * чтобы ошибочно внесённую оплату можно было отменить кнопкой.
     */
    public function up(): void
    {
        Schema::table('course_user', function (Blueprint $table) {
            $table->timestamp('admin_reminded_at')->nullable();
            $table->foreignId('admin_reminded_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->json('rollback_state')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('rollback_state');
        });

        Schema::table('course_user', function (Blueprint $table) {
            $table->dropConstrainedForeignId('admin_reminded_by');
            $table->dropColumn('admin_reminded_at');
        });
    }
};
