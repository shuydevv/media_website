<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Текст автосообщения бота воронки, изменённый в админке. Строка есть только
 * для отредактированных ключей — остальные берутся из config/telegram_funnel.php
 * (см. App\Service\Telegram\FunnelTexts).
 */
class TelegramText extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'text'];
}
