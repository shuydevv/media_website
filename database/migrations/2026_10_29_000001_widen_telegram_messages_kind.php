<?php

use App\Models\TelegramMessage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * telegram_messages.kind был VARCHAR(8), а kind рассылки — broadcast (9):
 * на MySQL запись истории падала сразу после отправки, и задача рассылки
 * останавливалась на каждом получателе (сторож перезапускал её раз в 3 мин —
 * по человеку за раз). SQLite в тестах длину не проверяет.
 *
 * Заодно — история для тех, кому рассылка уже ушла, а запись не сохранилась.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE telegram_messages MODIFY kind VARCHAR(16) NOT NULL');
        } else {
            Schema::table('telegram_messages', fn (Blueprint $table) => $table->string('kind', 16)->change());
        }

        $sent = DB::table('telegram_broadcast_recipients as r')
            ->join('telegram_broadcasts as b', 'b.id', '=', 'r.telegram_broadcast_id')
            ->where('r.status', 'sent')
            ->get(['r.telegram_contact_id', 'r.telegram_message_id', 'r.sent_at', 'b.id as broadcast_id', 'b.text', 'b.media_type']);

        foreach ($sent as $r) {
            $exists = DB::table('telegram_messages')->where('telegram_contact_id', $r->telegram_contact_id)
                ->where('kind', TelegramMessage::KIND_BROADCAST)->where('step', 'broadcast:'.$r->broadcast_id)->exists();
            if ($exists) {
                continue;
            }
            DB::table('telegram_messages')->insert([
                'telegram_contact_id' => $r->telegram_contact_id,
                'direction' => TelegramMessage::DIRECTION_OUT,
                'kind' => TelegramMessage::KIND_BROADCAST,
                'step' => 'broadcast:'.$r->broadcast_id,
                'text' => $r->text,
                'media_type' => $r->media_type,
                'telegram_message_id' => $r->telegram_message_id,
                'created_at' => $r->sent_at,
            ]);
        }
    }

    public function down(): void
    {
        // Сужать обратно нельзя — записи broadcast не влезут.
    }
};
