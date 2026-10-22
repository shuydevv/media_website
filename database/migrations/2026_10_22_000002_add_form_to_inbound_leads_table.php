<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** С какой формы на сайте пришла заявка («Шапка», «Попап — тарифы») — как прислал сайт. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inbound_leads', function (Blueprint $table) {
            $table->string('form', 100)->nullable()->after('site');
        });
    }

    public function down(): void
    {
        Schema::table('inbound_leads', function (Blueprint $table) {
            $table->dropColumn('form');
        });
    }
};
