<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «Не учитывать в CRM» — свои/тестовые аккаунты (фейки) с настоящим доступом
 * к курсам. Доступ не трогаем, но в статусы, счётчики, «Пользователей», доход
 * и дашборд такие ученики не попадают — живут на отдельной вкладке CRM.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('crm_excluded')->default(false)->after('is_demo');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('crm_excluded');
        });
    }
};
