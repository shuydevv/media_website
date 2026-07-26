<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * mock_number — ручной номер пробника (задаётся админом при создании
     * домашки type=mock), нужен для карточек на student/mocks.
     *
     * started_at/expires_at — таймер прохождения пробника (фиксированные
     * 3ч30м, см. Homework::MOCK_TIME_LIMIT_MINUTES). Проверяется реактивно,
     * по той же схеме, что и due_at — без cron/очередей.
     */
    public function up(): void
    {
        if (Schema::hasTable('homeworks') && !Schema::hasColumn('homeworks', 'mock_number')) {
            Schema::table('homeworks', function (Blueprint $table) {
                $table->unsignedSmallInteger('mock_number')->nullable()->after('type');
            });
        }

        if (Schema::hasTable('submissions') && !Schema::hasColumn('submissions', 'started_at')) {
            Schema::table('submissions', function (Blueprint $table) {
                $table->timestamp('started_at')->nullable()->after('status');
                $table->timestamp('expires_at')->nullable()->after('started_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('homeworks') && Schema::hasColumn('homeworks', 'mock_number')) {
            Schema::table('homeworks', function (Blueprint $table) {
                $table->dropColumn('mock_number');
            });
        }

        if (Schema::hasTable('submissions') && Schema::hasColumn('submissions', 'started_at')) {
            Schema::table('submissions', function (Blueprint $table) {
                $table->dropColumn(['started_at', 'expires_at']);
            });
        }
    }
};
