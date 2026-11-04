<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Тренажёр дат по истории — устроен как тренажёр терминов (см. миграцию
     * 2026_10_11_000001), таблицы свои. Элемент тренажёра — событие или
     * правление (hist_events.kind). Все таблицы явно InnoDB: расписание и
     * корм меняются под lockForUpdate() в транзакции.
     */
    public function up(): void
    {
        Schema::create('hist_events', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            // Ключ перезаливки — id из docs/history/events_base.json.
            $table->string('slug', 120)->unique();
            $table->string('kind', 8); // event | reign
            $table->string('title');
            $table->json('other_names')->nullable();
            $table->string('summary', 500)->nullable();
            // Как дата пишется на карточке («27 июня 1709») и годы, которые
            // ученик вводит: год окончания — только у диапазонов. У текущего
            // правления его нет вовсе (year_end = null).
            $table->string('date_display', 60);
            $table->unsignedSmallInteger('year_start');
            $table->unsignedSmallInteger('year_end')->nullable();
            $table->unsignedTinyInteger('era');
            $table->unsignedTinyInteger('level')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('parent_id')->nullable()->constrained('hist_events')->nullOnDelete();
            // Заметки автора к парам «их путают»: slug другого элемента => текст.
            $table->json('pair_notes')->nullable();
            $table->string('image')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('hist_facts', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('event_id')->constrained('hist_events')->cascadeOnDelete();
            // «poltava-1709__f3»: ключ перезаливки, на него ссылаются репорты.
            $table->string('key', 160)->unique();
            $table->unsignedSmallInteger('position');
            $table->string('type', 8); // person | detail | cause | result
            $table->string('text', 500);
            $table->string('group', 40)->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['event_id', 'position']);
        });

        Schema::create('hist_event_confusables', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->foreignId('event_id')->constrained('hist_events')->cascadeOnDelete();
            $table->foreignId('confusable_id')->constrained('hist_events')->cascadeOnDelete();

            $table->primary(['event_id', 'confusable_id']);
        });

        // Событие → правления, в которые оно началось (поле reigns в JSON).
        Schema::create('hist_event_reigns', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->foreignId('event_id')->constrained('hist_events')->cascadeOnDelete();
            $table->foreignId('reign_id')->constrained('hist_events')->cascadeOnDelete();

            $table->primary(['event_id', 'reign_id']);
        });

        Schema::create('user_hist_events', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('hist_events')->cascadeOnDelete();
            $table->unsignedTinyInteger('step')->default(1);
            $table->date('due_on');
            $table->date('learned_on');
            $table->date('last_reviewed_on')->nullable();
            // 'a' — ввод года, 'b' — задание на факты; чередуются.
            $table->char('next_method', 1)->default('a');
            $table->boolean('last_failed')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'event_id']);
            $table->index(['user_id', 'due_on']);
        });

        Schema::create('hist_sessions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 8); // learn | review | free
            $table->date('day');
            $table->json('state');
            $table->unsignedSmallInteger('total')->default(0);
            $table->unsignedSmallInteger('correct_first')->default(0);
            $table->unsignedTinyInteger('corm_awarded')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'kind', 'day']);
        });

        Schema::create('hist_reports', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('event_id')->constrained('hist_events')->cascadeOnDelete();
            $table->foreignId('fact_id')->constrained('hist_facts')->cascadeOnDelete();
            $table->json('distractor_ids');
            $table->string('comment', 500)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['fact_id', 'resolved_at']);
        });

        Schema::create('hist_trainer_courses', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->foreignId('course_id')->primary()->constrained('courses')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('hist_trainer_users', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hist_trainer_users');
        Schema::dropIfExists('hist_trainer_courses');
        Schema::dropIfExists('hist_reports');
        Schema::dropIfExists('hist_sessions');
        Schema::dropIfExists('user_hist_events');
        Schema::dropIfExists('hist_event_reigns');
        Schema::dropIfExists('hist_event_confusables');
        Schema::dropIfExists('hist_facts');
        Schema::dropIfExists('hist_events');
    }
};
