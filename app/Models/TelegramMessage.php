<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Одно сообщение переписки с ботом воронки — единственное место, где
 * хранится история (Bot API её не отдаёт). См. миграцию
 * create_telegram_funnel_tables.
 */
class TelegramMessage extends Model
{
    public const UPDATED_AT = null;

    public const DIRECTION_IN = 'in';

    public const DIRECTION_OUT = 'out';

    public const KIND_USER = 'user';

    public const KIND_AUTO = 'auto';

    public const KIND_ADMIN = 'admin';

    protected $fillable = [
        'telegram_contact_id', 'direction', 'kind', 'step',
        'text', 'media_type', 'file_id', 'telegram_message_id',
    ];

    protected $casts = [
        'forwarded_at' => 'datetime',
        'forward_claimed_at' => 'datetime',
        'forward_alerted_at' => 'datetime',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(TelegramContact::class, 'telegram_contact_id');
    }
}
