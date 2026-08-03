<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * CourseSession::lesson() объявлен как hasOne — модели предполагают строго
 * 1 урок на занятие, но в БД ничего это не гарантировало (StoreRequest тоже
 * проверял только exists:course_sessions,id, без unique — исправлено в той
 * же партии правок). Без индекса второй Lesson с тем же course_session_id
 * (двойной сабмит формы) реально создавался и оставался в БД, но hasOne
 * молча показывал только один из двух — второй пропадал из вида на странице
 * курса и на дашборде, оставаясь доступным только по прямой ссылке.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('lessons')
            ->select('course_session_id')
            ->whereNotNull('course_session_id')
            ->groupBy('course_session_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('course_session_id');

        if ($duplicates->isNotEmpty()) {
            // Не роняем миграцию на уже накопившихся дублях — просто не
            // добавляем индекс и явно предупреждаем, какие занятия задеты,
            // чтобы админ вручную разобрался, какой из уроков лишний.
            Log::warning('lessons: найдены дубли course_session_id, unique-индекс не добавлен', [
                'course_session_id' => $duplicates->all(),
            ]);

            return;
        }

        Schema::table('lessons', function (Blueprint $table) {
            $table->unique('course_session_id');
        });
    }

    public function down(): void
    {
        try {
            Schema::table('lessons', function (Blueprint $table) {
                $table->dropUnique(['course_session_id']);
            });
        } catch (\Throwable $e) {
            // Индекс мог не быть создан в up() (см. guard на дубли выше).
        }
    }
};
