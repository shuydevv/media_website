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
            // Кто из админов создал карточку вручную (Admin\User\StoreController) —
            // отличает реального приглашённого лида от того, кто сам пришёл через
            // публичную OTP-регистрацию и просто ни разу не подтвердил почту/телефон
            // (бот или бросивший на середине). См. User::scopeVisibleInCrm().
            // Без ->constrained() — users всё ещё MyISAM (см. CLAUDE.md), внешние
            // ключи там не работают, реальное ограничение всё равно не встанет.
            $table->unsignedBigInteger('created_by_admin_id')->nullable()->after('role');
            $table->index('created_by_admin_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['created_by_admin_id']);
            $table->dropColumn('created_by_admin_id');
        });
    }
};
