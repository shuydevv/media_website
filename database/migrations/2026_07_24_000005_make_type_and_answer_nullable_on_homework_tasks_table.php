<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * type/answer были NOT NULL, потому что раньше HomeworkTask ВСЕГДА нёс своё
 * содержание. Теперь строка-связка с банком (task_id заполнен) не обязана
 * иметь собственные type/answer — они читаются через прокси из Task (см.
 * HomeworkTask::getAttribute()). «Одноразовые» задания (task_id пуст)
 * по-прежнему заполняют оба поля как раньше — на уровне приложения, не БД.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('homework_tasks')) {
            return;
        }

        if (Schema::hasColumn('homework_tasks', 'type')) {
            DB::statement('ALTER TABLE homework_tasks MODIFY type VARCHAR(191) NULL');
        }
        if (Schema::hasColumn('homework_tasks', 'answer')) {
            DB::statement('ALTER TABLE homework_tasks MODIFY answer TEXT NULL');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('homework_tasks')) {
            return;
        }

        if (Schema::hasColumn('homework_tasks', 'type')) {
            DB::statement("UPDATE homework_tasks SET type = '' WHERE type IS NULL");
            DB::statement('ALTER TABLE homework_tasks MODIFY type VARCHAR(191) NOT NULL');
        }
        if (Schema::hasColumn('homework_tasks', 'answer')) {
            DB::statement("UPDATE homework_tasks SET answer = '' WHERE answer IS NULL");
            DB::statement('ALTER TABLE homework_tasks MODIFY answer TEXT NOT NULL');
        }
    }
};
