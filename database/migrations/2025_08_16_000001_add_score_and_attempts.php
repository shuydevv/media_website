<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // submissions
        Schema::table('submissions', function (Blueprint $table) {
            if (!Schema::hasColumn('submissions', 'attempt_no')) {
                $table->unsignedTinyInteger('attempt_no')->default(1)->after('user_id');
            }
            if (!Schema::hasColumn('submissions', 'autocheck_score')) {
                $table->unsignedSmallInteger('autocheck_score')->nullable()->after('attempt_no');
            }
            if (!Schema::hasColumn('submissions', 'manual_score')) {
                $table->unsignedSmallInteger('manual_score')->nullable()->after('autocheck_score');
            }
            if (!Schema::hasColumn('submissions', 'total_score')) {
                $table->unsignedSmallInteger('total_score')->nullable()->after('manual_score');
            }
            if (!Schema::hasColumn('submissions', 'status')) {
                $table->string('status')->default('pending')->after('total_score');
            }
            if (!Schema::hasColumn('submissions', 'per_task_results')) {
                $table->json('per_task_results')->nullable()->after('status');
            }
        });

        // homeworks
        if (Schema::hasTable('homeworks') && !Schema::hasColumn('homeworks', 'attempts_allowed')) {
            Schema::table('homeworks', function (Blueprint $table) {
                $table->unsignedTinyInteger('attempts_allowed')->default(2)->after('lesson_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            if (Schema::hasColumn('submissions', 'attempt_no')) $table->dropColumn('attempt_no');
            if (Schema::hasColumn('submissions', 'autocheck_score')) $table->dropColumn('autocheck_score');
            if (Schema::hasColumn('submissions', 'manual_score')) $table->dropColumn('manual_score');
            if (Schema::hasColumn('submissions', 'total_score')) $table->dropColumn('total_score');
            if (Schema::hasColumn('submissions', 'status')) $table->dropColumn('status');
            if (Schema::hasColumn('submissions', 'per_task_results')) $table->dropColumn('per_task_results');
        });

        if (Schema::hasTable('homeworks') && Schema::hasColumn('homeworks', 'attempts_allowed')) {
            Schema::table('homeworks', function (Blueprint $table) {
                $table->dropColumn('attempts_allowed');
            });
        }
    }
};
