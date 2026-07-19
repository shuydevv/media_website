<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_user', function (Blueprint $table) {
            $table->foreignId('promo_code_id')->nullable()->after('promo_code')
                ->constrained('promo_codes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('course_user', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promo_code_id');
        });
    }
};
