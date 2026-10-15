<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Оценка 👍/👎 под бонусным уроком — отдельно от основного (rating):
 * человек может по-разному оценить лёгкий и сложный урок, а по аналитике
 * видно, какой из двух заходит.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_contacts', function (Blueprint $table) {
            $table->string('bonus_rating', 8)->nullable()->after('rating_message_id');
            $table->timestamp('bonus_rated_at')->nullable()->after('bonus_rating');
            $table->unsignedBigInteger('bonus_rating_message_id')->nullable()->after('bonus_rated_at');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_contacts', function (Blueprint $table) {
            $table->dropColumn(['bonus_rating', 'bonus_rated_at', 'bonus_rating_message_id']);
        });
    }
};
