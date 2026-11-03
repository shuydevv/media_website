<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Входящее сообщение Авито — см. Avito\WebhookController и SendAvitoMessage.
 */
class AvitoMessage extends Model
{
    protected $fillable = ['message_id', 'chat_id', 'user_id', 'author_id', 'item_id', 'type', 'text', 'sent_at_avito', 'sent_at'];

    protected $casts = [
        'sent_at_avito' => 'datetime',
        'sent_at' => 'datetime',
    ];

    /** Чат в веб-версии мессенджера Авито. */
    public function chatUrl(): string
    {
        return 'https://www.avito.ru/profile/messenger/channel/'.rawurlencode($this->chat_id);
    }

    /** Первое сообщение в этом чате — значит, новый чат. */
    public function opensChat(): bool
    {
        return ! self::where('chat_id', $this->chat_id)->where('id', '<', $this->id)->exists();
    }
}
