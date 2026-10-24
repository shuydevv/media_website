<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Доставка сообщений человека в его тему админ-группы. Раньше пересылка
 * была одной задачей очереди: исчерпала попытки — сообщение осталось только
 * в failed_jobs, и в группе его никто не видел (23.10.2026, кэш-папка без
 * прав → не создалась тема). Теперь пересылка отмечается здесь, а
 * telegram:resend-unforwarded досылает всё, что без отметки.
 *
 * forward_claimed_at — «взял в работу» для досылки: два запуска команды не
 * перешлют одно сообщение дважды (без кэш-мьютекса withoutOverlapping).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_messages', function (Blueprint $table) {
            $table->timestamp('forwarded_at')->nullable();
            $table->timestamp('forward_claimed_at')->nullable();
            $table->unsignedSmallInteger('forward_failures')->default(0);
            $table->string('forward_error')->nullable();
            $table->timestamp('forward_alerted_at')->nullable();
            $table->index(['kind', 'forwarded_at']);
        });

        // Всё, что было до этой миграции, считаем пересланным: досылать
        // месячную историю в темы не нужно.
        DB::table('telegram_messages')->where('kind', 'user')->update(['forwarded_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('telegram_messages', function (Blueprint $table) {
            $table->dropIndex(['kind', 'forwarded_at']);
            $table->dropColumn(['forwarded_at', 'forward_claimed_at', 'forward_failures', 'forward_error', 'forward_alerted_at']);
        });
    }
};
