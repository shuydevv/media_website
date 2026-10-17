<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class ChangeAnswerColumnToTextInHomeworkTasksTable extends Migration
{
    public function up(): void
    {
        // ENGINE/MODIFY — синтаксис MySQL. Тесты гоняются на SQLite (phpunit.xml),
        // там это не нужно.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE homework_tasks MODIFY answer TEXT NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE homework_tasks MODIFY answer VARCHAR(255) NOT NULL');
    }
}
