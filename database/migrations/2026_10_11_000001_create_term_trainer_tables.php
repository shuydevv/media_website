<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Тренажёр терминов (задание 18) — механика в docs/terms/. Все таблицы
     * явно InnoDB: расписание и корм за сессию меняются под lockForUpdate()
     * в транзакции, на MyISAM это молча не работало бы (см. CLAUDE.md).
     */
    public function up(): void
    {
        Schema::create('terms', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            // Ключ перезаливки: по нему импорт находит термин, id из файла
            // (source_id) может поменяться, slug — нет.
            $table->string('slug', 120)->unique();
            $table->unsignedInteger('source_id')->nullable();
            // title — то, что ученик вводит в квадратики; qualifier («как
            // система») не вводится, other_names засчитываются как ответ.
            $table->string('title');
            $table->string('qualifier')->nullable();
            $table->json('other_names')->nullable();
            // image — имя файла из JSON, по нему сопоставляется загруженное
            // фото. Сами файлы лежат под обезличенными именами (image_path /
            // image_blur_path): исходное имя совпадает с названием термина и
            // выдавало бы ответ в URL.
            $table->string('image')->nullable();
            $table->string('image_path')->nullable();
            $table->string('image_blur_path')->nullable();
            $table->string('code', 8);
            $table->unsignedTinyInteger('section');
            $table->unsignedTinyInteger('priority')->default(2);
            // Порядок изучения: приоритет → раздел → тема → source_id,
            // считается при импорте (TermImporter::sortOrder()).
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('parent_id')->nullable()->constrained('terms')->nullOnDelete();
            // Термин, исчезнувший из файла при перезаливке, не удаляется
            // (на него ссылается прогресс учеников), а скрывается.
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('term_features', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();
            // id признака из JSON («lichnost__f1»): ключ перезаливки, на него
            // же ссылаются репорты — после запуска не перенумеровывать.
            $table->string('key', 160)->unique();
            $table->unsignedSmallInteger('position');
            $table->text('text');
            $table->string('group', 16)->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['term_id', 'position']);
        });

        Schema::create('term_confusables', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();
            $table->foreignId('confusable_term_id')->constrained('terms')->cascadeOnDelete();

            $table->primary(['term_id', 'confusable_term_id']);
        });

        Schema::create('user_terms', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();
            $table->unsignedTinyInteger('step')->default(1);
            // Даты без времени: изучил сегодня в любое время — повторение завтра.
            $table->date('due_on');
            $table->date('learned_on');
            $table->date('last_reviewed_on')->nullable();
            // Каким способом проверять в следующий раз ('a' — квадратики,
            // 'b' — квиз), чередуется от повторения к повторению.
            $table->char('next_method', 1)->default('a');
            $table->timestamps();

            $table->unique(['user_id', 'term_id']);
            $table->index(['user_id', 'due_on']);
        });

        Schema::create('term_sessions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 8); // learn | review | free
            $table->date('day');
            // Очередь, текущая фаза, результаты первых попыток — всё, что
            // нужно, чтобы продолжить сессию с того же места.
            $table->json('state');
            $table->unsignedSmallInteger('total')->default(0);
            $table->unsignedSmallInteger('correct_first')->default(0);
            // null — корм за сессию ещё не начислялся (или она без корма).
            $table->unsignedTinyInteger('corm_awarded')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'kind', 'day']);
        });

        Schema::create('term_reports', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained('term_features')->cascadeOnDelete();
            $table->json('distractor_ids');
            $table->string('comment', 500)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['feature_id', 'resolved_at']);
        });

        // Доступ к тренажёру сверх курсов обществознания: дополнительные
        // курсы и личная выдача ученику без курсов.
        Schema::create('term_trainer_courses', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->foreignId('course_id')->primary()->constrained('courses')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('term_trainer_users', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('term_trainer_users');
        Schema::dropIfExists('term_trainer_courses');
        Schema::dropIfExists('term_reports');
        Schema::dropIfExists('term_sessions');
        Schema::dropIfExists('user_terms');
        Schema::dropIfExists('term_confusables');
        Schema::dropIfExists('term_features');
        Schema::dropIfExists('terms');
    }
};
