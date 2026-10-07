<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Поля под отчёт по ученику (/admin/users/{id}):
     *  - submissions.submitted_at — момент сдачи; updated_at для этого не
     *    годится, его перезаписывает проверка куратора;
     *  - submissions.task_meta — по каждому заданию: секунды на вопрос,
     *    число неверных проверок до сохранения, первая оценка, открывал ли
     *    подсказку. Отдельная колонка, а не ключи внутри per_task_results:
     *    тот целиком пересобирается в SubmissionController::finalize();
     *  - tasks.topic_id — тема задания (те же Topic/Section, что у упражнений
     *    и планов), чтобы считать слабые места не только по номеру в ЕГЭ;
     *  - course_user.target_score / entry_score — цель ученика и входной
     *    результат по курсу, в тестовых баллах (0–100).
     */
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->after('expires_at');
            $table->json('task_meta')->nullable()->after('per_task_results');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('topic_id')->nullable()->after('number');
            $table->index('topic_id');
        });

        Schema::table('course_user', function (Blueprint $table) {
            $table->unsignedTinyInteger('target_score')->nullable();
            $table->unsignedTinyInteger('entry_score')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn(['submitted_at', 'task_meta']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['topic_id']);
            $table->dropColumn('topic_id');
        });

        Schema::table('course_user', function (Blueprint $table) {
            $table->dropColumn(['target_score', 'entry_score']);
        });
    }
};
