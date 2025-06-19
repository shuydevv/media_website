<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('course_sessions', function (Blueprint $table) {
                    $table->integer('duration_minutes')->nullable()->after('end_time');
                    $table->boolean('is_cancelled')->default(false)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_sessions', function (Blueprint $table) {
                    $table->dropColumn(['duration_minutes', 'is_cancelled']);
        });
    }
};
