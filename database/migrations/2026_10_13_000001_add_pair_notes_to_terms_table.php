<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * pair_notes — короткие пояснения преподавателя к парам понятий, которые
     * путают: {"slug другого термина": "ВВП считают по территории, ВНП — по
     * производителю"}. Рыба говорит заметку дословно, когда ученик спутал
     * эти два понятия. Приходит из JSON базы терминов, как и всё остальное;
     * заметка действует в обе стороны, записывать её достаточно у одного
     * термина из пары.
     */
    public function up(): void
    {
        Schema::table('terms', function (Blueprint $table) {
            $table->json('pair_notes')->nullable()->after('other_names');
        });
    }

    public function down(): void
    {
        Schema::table('terms', function (Blueprint $table) {
            $table->dropColumn('pair_notes');
        });
    }
};
