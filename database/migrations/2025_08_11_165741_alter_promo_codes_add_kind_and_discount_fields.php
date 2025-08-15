<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('promo_codes', function (Blueprint $table) {
            // Назначение промокода
            $table->enum('kind', ['access','discount'])->default('access')->after('id');

            // Параметры скидки (для kind=discount)
            $table->enum('discount_mode', ['percent','amount','fixed_price','free'])->nullable()->after('kind');
            // Храним суммы в копейках/центах, чтобы не было ошибок округления
            $table->unsignedBigInteger('discount_value_cents')->nullable()->after('discount_mode'); // для amount/fixed_price
            $table->unsignedSmallInteger('discount_percent')->nullable()->after('discount_value_cents'); // 1..100
            $table->string('currency', 3)->nullable()->after('discount_percent'); // 'RUB','USD', ...

            // Для совместимости: duration_days уже есть — он нужен для kind=access
        });
    }

    public function down(): void
    {
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->dropColumn(['kind','discount_mode','discount_value_cents','discount_percent','currency']);
        });
    }
};
