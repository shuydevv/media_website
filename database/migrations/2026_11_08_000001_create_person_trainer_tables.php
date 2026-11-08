<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Тренажёр личностей по истории — устроен как тренажёр дат (миграция
     * 2026_10_29_000001), таблицы свои. Доступ общий с датами
     * (hist_trainer_courses / hist_trainer_users, App\Service\Dates\HistAccess).
     * Все таблицы явно InnoDB: расписание и корм меняются под lockForUpdate().
     */
    public function up(): void
    {
        Schema::create('persons', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            // Ключ перезаливки — id из docs/personalities/personalities.json.
            $table->string('slug', 120)->unique();
            $table->string('name', 191);
            // description — кто это; association — чем известен (событие, эпоха).
            $table->string('description', 500);
            $table->string('association', 500);
            // Связанные личности: связный текст и их slug (не больше двух).
            $table->string('related_text', 500)->nullable();
            $table->json('related_slugs')->nullable();
            $table->unsignedTinyInteger('era');
            // R правитель, M полководец, S гос. деятель, C церковь, K культура
            // и наука, O общественное движение, H герой — только для подбора
            // неверных вариантов.
            $table->char('role', 1);
            $table->unsignedTinyInteger('level')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            // Что вводят в клетки (фамилия); null — ввода для этой личности нет.
            $table->string('answer', 60)->nullable();
            $table->string('image_path')->nullable();
            // Откуда портрет: автор, лицензия, ссылка — для подписи.
            $table->json('image_meta')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'level', 'sort_order']);
        });

        Schema::create('user_persons', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->unsignedTinyInteger('step')->default(1);
            $table->date('due_on');
            $table->date('learned_on');
            $table->date('last_reviewed_on')->nullable();
            // 'b' — «Чем известен?», 'c' — «Кто это?», 'a' — ввод фамилии.
            $table->char('next_method', 1)->default('c');
            $table->boolean('last_failed')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'person_id']);
            $table->index(['user_id', 'due_on']);
        });

        Schema::create('person_sessions', function (Blueprint $table) {
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

        Schema::create('person_reports', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->char('step', 1); // b | c | d
            // Показанные варианты (id личностей) в порядке показа.
            $table->json('option_ids');
            $table->string('comment', 500)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['person_id', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_reports');
        Schema::dropIfExists('person_sessions');
        Schema::dropIfExists('user_persons');
        Schema::dropIfExists('persons');
    }
};
