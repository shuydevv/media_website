<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ученик убрал свою эмоцию. Строка не удаляется: по ней считается пауза
// между эмоциями (иначе «поставил — убрал — поставил» обходит её), но в
// итогах урока и отчёте по ученику такая эмоция не считается.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_emotions', function (Blueprint $table) {
            $table->timestamp('withdrawn_at')->nullable()->after('ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('chat_emotions', function (Blueprint $table) {
            $table->dropColumn('withdrawn_at');
        });
    }
};
