<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            // ENUM сохраняет читабельность значений. По умолчанию — theory.
            $table->enum('lesson_type', ['theory', 'practice'])->default('theory')->after('title');
            $table->index('lesson_type');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropIndex(['lesson_type']);
            $table->dropColumn('lesson_type');
        });
    }
};
