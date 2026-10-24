<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Несколько ботов (App\Service\Telegram\TelegramBots): контакт — это человек
 * в конкретном боте. Один и тот же Telegram-пользователь в боте воронки и в
 * боте диагностики — два контакта, поэтому уникальность — по паре
 * (telegram_user_id, bot), а не по telegram_user_id. telegram_user_id первым:
 * индекс годится и для поиска человека по всем ботам.
 *
 * lesson — урок воронки пробного урока; у контактов других ботов его нет.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_contacts', function (Blueprint $table) {
            $table->string('bot', 16)->default('lesson')->after('id');
        });

        Schema::table('telegram_contacts', function (Blueprint $table) {
            $table->unique(['telegram_user_id', 'bot']);
            $table->dropUnique(['telegram_user_id']);
        });

        if (DB::getDriverName() !== 'mysql') {
            // Тесты на SQLite (phpunit.xml): MODIFY — синтаксис MySQL.
            Schema::table('telegram_contacts', fn (Blueprint $table) => $table->string('lesson', 8)->nullable()->change());

            return;
        }

        DB::statement('ALTER TABLE telegram_contacts MODIFY lesson VARCHAR(8) NULL');
    }

    public function down(): void
    {
        // Вниз — только если других ботов ещё нет: иначе вернуть уникальность
        // telegram_user_id нельзя без удаления контактов.
        DB::table('telegram_contacts')->whereNull('lesson')->update(['lesson' => 'cho']);

        if (DB::getDriverName() !== 'mysql') {
            Schema::table('telegram_contacts', fn (Blueprint $table) => $table->string('lesson', 8)->nullable(false)->change());
        } else {
            DB::statement('ALTER TABLE telegram_contacts MODIFY lesson VARCHAR(8) NOT NULL');
        }

        Schema::table('telegram_contacts', function (Blueprint $table) {
            $table->unique(['telegram_user_id']);
            $table->dropUnique(['telegram_user_id', 'bot']);
        });

        Schema::table('telegram_contacts', function (Blueprint $table) {
            $table->dropColumn('bot');
        });
    }
};
