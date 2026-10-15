<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Оценка урока по трём уровням (great / good / bad вместо up / down) и учёт
 * просмотра на странице пробного урока — см. FunnelBot (шапка класса).
 *
 * Поля попарно: lesson_* — основной урок контакта, bonus_* — бонусный.
 * Все «отложенные» сообщения — полями *_due_at, которые раз в минуту
 * разбирает telegram:send-reminders: отменить такое сообщение — значит, что
 * в момент отправки не выполнится условие, искать задачу в очереди не нужно.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_contacts', function (Blueprint $table) {
            // Где поставлена оценка: bot | site.
            $table->string('rating_source', 8)->nullable()->after('rating_message_id');
            $table->string('bonus_rating_source', 8)->nullable()->after('bonus_rating_message_id');

            foreach (['lesson', 'bonus'] as $slot) {
                // Реально просмотренные секунды (без перемотки), длительность,
                // докуда дошёл (для «продолжить с того же места») и % просмотра.
                $table->unsignedInteger("{$slot}_watch_seconds")->default(0);
                $table->unsignedInteger("{$slot}_duration")->nullable();
                $table->unsignedInteger("{$slot}_position")->default(0);
                $table->unsignedTinyInteger("{$slot}_progress")->default(0);
                $table->timestamp("{$slot}_last_watch_at")->nullable();
                // Досмотрел до порога (80%) — с этого момента «полноценный просмотр».
                $table->timestamp("{$slot}_watched_at")->nullable();
                // «Как вам урок?» с кнопками — через 5 минут после досмотра.
                $table->timestamp("{$slot}_ask_due_at")->nullable()->index();
                $table->timestamp("{$slot}_ask_sent_at")->nullable();
            }

            // Напоминание тем, кто открыл урок и ушёл, не досмотрев (одно на человека).
            $table->timestamp('nudge_due_at')->nullable()->index();
            $table->timestamp('nudge_sent_at')->nullable();
            // Вопрос от Александра после хорошей оценки (один на человека).
            $table->timestamp('question_due_at')->nullable()->index();
            $table->timestamp('question_sent_at')->nullable();
        });

        foreach (['rating', 'bonus_rating'] as $column) {
            DB::table('telegram_contacts')->where($column, 'up')->update([$column => 'great']);
            DB::table('telegram_contacts')->where($column, 'down')->update([$column => 'bad']);
        }
    }

    public function down(): void
    {
        foreach (['rating', 'bonus_rating'] as $column) {
            DB::table('telegram_contacts')->whereIn($column, ['great', 'good'])->update([$column => 'up']);
            DB::table('telegram_contacts')->where($column, 'bad')->update([$column => 'down']);
        }

        Schema::table('telegram_contacts', function (Blueprint $table) {
            $columns = ['rating_source', 'bonus_rating_source', 'nudge_due_at', 'nudge_sent_at', 'question_due_at', 'question_sent_at'];
            foreach (['lesson', 'bonus'] as $slot) {
                foreach (['watch_seconds', 'duration', 'position', 'progress', 'last_watch_at', 'watched_at', 'ask_due_at', 'ask_sent_at'] as $f) {
                    $columns[] = "{$slot}_{$f}";
                }
            }
            $table->dropColumn($columns);
        });
    }
};
