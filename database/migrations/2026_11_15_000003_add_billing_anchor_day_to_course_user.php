<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Число месяца, к которому привязан помесячный график оплаты (16 → каждое
 * 16-е). Задаётся первой оплатой, возвратом после долгого перерыва и ручной
 * датой из CRM; см. BillingService::scheduleAfterPayment(). Пусто у старых
 * записей — для них берётся число первой оплаты с суммой.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_user', function (Blueprint $table) {
            $table->unsignedTinyInteger('billing_anchor_day')->nullable()->after('billing_interval_days');
        });
    }

    public function down(): void
    {
        Schema::table('course_user', function (Blueprint $table) {
            $table->dropColumn('billing_anchor_day');
        });
    }
};
