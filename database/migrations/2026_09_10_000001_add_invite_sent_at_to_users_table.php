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
            // Когда последний раз генерировалась ссылка-приглашение
            // (UserInviteService::send()) — показывается админу на
            // edit-странице, чтобы было видно, что ссылка могла протухнуть
            // (живёт UserInviteService::DAYS_VALID дней) и её пора обновить.
            $table->timestamp('invite_sent_at')->nullable()->after('created_by_admin_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('invite_sent_at');
        });
    }
};
