<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Получатель рассылки — строка заводится в момент старта отправки
 * (BroadcastSender::start). pending — в очереди; sending — захвачен
 * задачей, отправка идёт; sent — доставлено;
 * blocked — заблокировал бота; failed — другая ошибка Telegram (не
 * повторяем: при обрыве связи сообщение могло дойти, повтор дал бы дубль).
 */
class TelegramBroadcastRecipient extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_FAILED = 'failed';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'sent_at' => 'datetime',
        'replied_at' => 'datetime',
    ];

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(TelegramBroadcast::class, 'telegram_broadcast_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(TelegramContact::class, 'telegram_contact_id');
    }
}
