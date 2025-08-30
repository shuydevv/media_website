<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            // На SQLite тип json будет создан как TEXT — это нормально
            if (!Schema::hasColumn('submissions', 'ai_drafts')) {
                $table->json('ai_drafts')->nullable()->after('data'); // подправь after под свою схему
            }
            if (!Schema::hasColumn('submissions', 'ai_frozen_hash')) {
                $table->json('ai_frozen_hash')->nullable()->after('ai_drafts');
            }
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            if (Schema::hasColumn('submissions', 'ai_drafts')) {
                $table->dropColumn('ai_drafts');
            }
            if (Schema::hasColumn('submissions', 'ai_frozen_hash')) {
                $table->dropColumn('ai_frozen_hash');
            }
        });
    }
};
