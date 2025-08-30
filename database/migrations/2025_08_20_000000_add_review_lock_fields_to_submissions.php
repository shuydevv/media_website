<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $t) {
            // кто держит работу на проверке
            if (!Schema::hasColumn('submissions', 'locked_by')) {
                $t->unsignedBigInteger('locked_by')->nullable()->index();
            }
            if (!Schema::hasColumn('submissions', 'lock_expires_at')) {
                $t->timestamp('lock_expires_at')->nullable()->index();
            }

            // (опционально) доп. поля, если их ещё нет — для будущей логики
            if (!Schema::hasColumn('submissions', 'autocheck_score')) {
                $t->unsignedInteger('autocheck_score')->nullable();
            }
            if (!Schema::hasColumn('submissions', 'manual_score')) {
                $t->unsignedInteger('manual_score')->nullable();
            }
            if (!Schema::hasColumn('submissions', 'total_score')) {
                $t->unsignedInteger('total_score')->nullable();
            }
            if (!Schema::hasColumn('submissions', 'per_task_results')) {
                $t->json('per_task_results')->nullable();
            }
            if (!Schema::hasColumn('submissions', 'attempt_no')) {
                $t->unsignedInteger('attempt_no')->default(1);
            }
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $t) {
            $cols = [
                'locked_by','lock_expires_at',
                'autocheck_score','manual_score','total_score',
                'per_task_results','attempt_no',
            ];
            foreach ($cols as $c) {
                if (Schema::hasColumn('submissions', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
    }
};
