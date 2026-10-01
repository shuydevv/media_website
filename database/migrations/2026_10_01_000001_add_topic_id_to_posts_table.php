<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Тема (а через неё — раздел) у поста. Нужна планам ЕГЭ (посты с тегом
 * «Планы»): по ней список /plans группируется по разделам и темам.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('topic_id')->nullable()->after('category_id')
                ->constrained('topics')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('topic_id');
        });
    }
};
