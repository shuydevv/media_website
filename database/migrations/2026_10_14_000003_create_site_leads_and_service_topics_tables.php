<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * site_leads — заявки из формы «Записаться» на сайте (SiteLeadController).
 * Сначала пишутся сюда, потом уходят в админ-группу задачей очереди: если
 * Telegram в этот момент недоступен, заявка не теряется (sent_at = null —
 * значит, в группу ещё не дошла).
 *
 * telegram_service_topics — служебные темы админ-группы бота воронки
 * («📥 Старты», «📝 Заявки с сайта»): заводятся ботом один раз, id темы
 * хранится здесь, а не в кэше — кэш чистится, и тогда бот завёл бы тему
 * заново, дублем.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_leads', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            // Одно из двух: логин Telegram (без @) или телефон +7… — логина
            // у многих нет (см. App\Service\Telegram\LeadContact).
            $table->string('telegram', 32)->nullable();
            $table->string('phone', 20)->nullable();
            // Какая кнопка открыла форму (data-open-form / data-label) и на какой странице.
            $table->string('cta', 64)->nullable();
            $table->string('cta_label')->nullable();
            $table->string('page', 500)->nullable();
            // Ссылка на бота со страницы «Спасибо» (t.me/bot?start=lead_<token>):
            // запустил — заявка связывается с контактом бота, и админу можно
            // отвечать через бота. Токен, а не id: id перебирается.
            $table->string('token', 24)->unique();
            $table->foreignId('telegram_contact_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });

        Schema::create('telegram_service_topics', function (Blueprint $table) {
            $table->string('key', 32)->primary();
            $table->unsignedBigInteger('topic_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_service_topics');
        Schema::dropIfExists('site_leads');
    }
};
