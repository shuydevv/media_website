<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Ручная стадия чеклиста CRM (/admin/crm) — взаимоисключающий выбор
            // одного из трёх (не независимые флаги: "связались" и "пробный
            // урок пройден" не могут стоять одновременно). То, что система
            // не может определить сама — оплата/регистрация видны из
            // course_user/email_verified_at и здесь не дублируются, см.
            // User::crmStatus().
            $table->string('crm_stage')->nullable()->after('profile_completed_at');
            $table->text('crm_note')->nullable()->after('crm_stage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['crm_stage', 'crm_note']);
        });
    }
};
