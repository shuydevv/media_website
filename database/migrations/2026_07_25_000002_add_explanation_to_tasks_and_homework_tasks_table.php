<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('tasks', 'explanation')) {
                $table->text('explanation')->nullable()->after('hint');
            }
        });

        Schema::table('homework_tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('homework_tasks', 'explanation')) {
                $table->text('explanation')->nullable()->after('hint');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (Schema::hasColumn('tasks', 'explanation')) {
                $table->dropColumn('explanation');
            }
        });

        Schema::table('homework_tasks', function (Blueprint $table) {
            if (Schema::hasColumn('homework_tasks', 'explanation')) {
                $table->dropColumn('explanation');
            }
        });
    }
};
