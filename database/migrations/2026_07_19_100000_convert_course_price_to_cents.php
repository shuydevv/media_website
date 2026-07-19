<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->unsignedInteger('price_cents')->default(0)->after('price');
            $table->unsignedInteger('old_price_cents')->nullable()->after('old_price');
        });

        DB::table('courses')->select('id', 'price', 'old_price')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                DB::table('courses')->where('id', $row->id)->update([
                    'price_cents' => self::parseToCents($row->price),
                    'old_price_cents' => $row->old_price !== null ? self::parseToCents($row->old_price) : null,
                ]);
            }
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['price', 'old_price']);
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('price')->default('0')->after('price_cents');
            $table->string('old_price')->nullable()->after('old_price_cents');
        });

        DB::table('courses')->select('id', 'price_cents', 'old_price_cents')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                DB::table('courses')->where('id', $row->id)->update([
                    'price' => (string) round($row->price_cents / 100, 2),
                    'old_price' => $row->old_price_cents !== null ? (string) round($row->old_price_cents / 100, 2) : null,
                ]);
            }
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['price_cents', 'old_price_cents']);
        });
    }

    /**
     * Та же логика парсинга, что была в CourseCheckoutController::basePriceCents() —
     * вшита сюда, чтобы миграция не зависела от кода контроллера, который эта же
     * миграция и делает ненужным.
     */
    private static function parseToCents(?string $raw): int
    {
        $raw = (string) ($raw ?? '0');
        $normalized = str_replace(',', '.', preg_replace('/[^\d,\.]/', '', $raw));
        $float = is_numeric($normalized) ? (float) $normalized : 0.0;

        return (int) round($float * 100);
    }
};
