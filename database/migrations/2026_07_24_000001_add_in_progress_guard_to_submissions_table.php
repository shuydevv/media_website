<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Не даёт создать вторую in_progress-попытку по одной домашке для одного
     * ученика (гонка при двойном клике/двух вкладках — раньше проверялась
     * только на уровне приложения, между SELECT и INSERT, без гарантии).
     * submissions — таблица MyISAM (не InnoDB): DB::transaction()/
     * lockForUpdate() тут не дают настоящей атомарности (MyISAM не умеет
     * ни то, ни другое), поэтому защита не через блокировку, а через
     * уникальный индекс — единственное, что MyISAM всё же соблюдает.
     *
     * in_progress_slot — STORED generated column (не VIRTUAL: на VIRTUAL
     * MyISAM не строит индекс), 1 когда status = in_progress, иначе NULL.
     * NULL в уникальном индексе не считается совпадением сам с собой, так
     * что сколько угодно завершённых попыток по одной домашке спокойно
     * сосуществуют — ограничение бьёт только по двум одновременным
     * in_progress.
     */
    public function up(): void
    {
        if (Schema::hasTable('submissions') && !Schema::hasColumn('submissions', 'in_progress_slot')) {
            Schema::table('submissions', function (Blueprint $table) {
                $table->unsignedTinyInteger('in_progress_slot')
                    ->nullable()
                    ->storedAs("IF(status = 'in_progress', 1, NULL)")
                    ->after('status');

                $table->unique(['homework_id', 'user_id', 'in_progress_slot'], 'submissions_one_in_progress_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('submissions') && Schema::hasColumn('submissions', 'in_progress_slot')) {
            Schema::table('submissions', function (Blueprint $table) {
                $table->dropUnique('submissions_one_in_progress_unique');
                $table->dropColumn('in_progress_slot');
            });
        }
    }
};
