<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Входящее сообщение Авито — см. Avito\WebhookController и SendAvitoMessage.
 */
class AvitoMessage extends Model
{
    protected $fillable = ['message_id', 'chat_id', 'user_id', 'author_id', 'item_id', 'type', 'direction', 'from_bot', 'text', 'telegram_message_id', 'sent_at_avito', 'sent_at'];

    public const IN = 'in';

    public const OUT = 'out';

    protected $casts = [
        'from_bot' => 'boolean',
        'sent_at_avito' => 'datetime',
        'sent_at' => 'datetime',
    ];

    /** Чат в веб-версии мессенджера Авито. */
    public function chatUrl(): string
    {
        return 'https://www.avito.ru/profile/messenger/channel/'.rawurlencode($this->chat_id);
    }
}
