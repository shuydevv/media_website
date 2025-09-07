<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 32)->nullable()->unique()->after('email');
            }
            if (!Schema::hasColumn('users', 'phone_verified_at')) {
                $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
            }
            if (!Schema::hasColumn('users', 'timezone')) {
                $table->string('timezone', 64)->nullable()->default(config('app.timezone'))->after('phone');
            }
            if (!Schema::hasColumn('users', 'locale')) {
                $table->string('locale', 8)->nullable()->default('ru')->after('timezone');
            }
        });
    }
    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
            $cols = ['phone','phone_verified_at','timezone','locale'];
            foreach ($cols as $c) { if (Schema::hasColumn('users',$c)) $table->dropColumn($c); }
        });
    }
};
