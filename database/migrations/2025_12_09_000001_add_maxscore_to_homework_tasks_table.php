<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('homework_tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('homework_tasks', 'max_score')) {
                $table->text('max_score')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('homework_tasks', function (Blueprint $table) {
            if (Schema::hasColumn('homework_tasks', 'max_score')) {
                $table->dropColumn('max_score');
            }
        });
    }
};
