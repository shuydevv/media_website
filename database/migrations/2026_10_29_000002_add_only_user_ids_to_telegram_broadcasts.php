<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * only_user_ids — рассылка только этим людям (telegram_user_id; список
 * вставляют ответом на карточку «только 123 456 …», id копируют в админке
 * в списке контактов). null — всем из аудитории.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_broadcasts', function (Blueprint $table) {
            $table->json('only_user_ids')->nullable()->after('excluded_user_ids');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_broadcasts', function (Blueprint $table) {
            $table->dropColumn('only_user_ids');
        });
    }
};
