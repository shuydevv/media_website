<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Звание ученика в тренажёре личностей (Адмирал Клешня): номер последнего
     * врученного звания из config('persons.ranks'). 0 — с адмиралом ещё не
     * знакомы. Звания не отбираются, поэтому храним, а не считаем на лету —
     * заодно окно «Приказ по флоту» показывается ровно один раз.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('persons_rank')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('persons_rank');
        });
    }
};
