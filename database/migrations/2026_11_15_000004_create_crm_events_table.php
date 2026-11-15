<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * История работы с учеником в CRM (лента в карточке): смена статуса,
 * «Напомнил», отказ с причиной, «Не учитывать». Платежи в ленту берутся
 * из payments — сюда не дублируются. crm_lost_reason — причина отказа
 * (ключ из App\Models\User::CRM_LOST_REASONS), для сводки в архиве.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 32);
            $table->string('text', 500);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('crm_lost_reason', 32)->nullable()->after('crm_stage');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('crm_lost_reason');
        });
        Schema::dropIfExists('crm_events');
    }
};
