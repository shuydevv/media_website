<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Пачка ИИ-проверки: выгрузка для Claude Desktop (channel=desktop, см.
 * DesktopGradingExchange) или Message Batch в Claude API (channel=api, см.
 * ClaudeBatchGrader).
 */
class AiGradingBatch extends Model
{
    public const CHANNEL_DESKTOP = 'desktop';
    public const CHANNEL_API = 'api';

    protected $fillable = [
        'channel',
        'external_id',
        'status',
        'request_count',
        'succeeded_count',
        'failed_count',
        'items',
        'created_by',
        'ended_at',
    ];

    protected $casts = [
        'ended_at' => 'datetime',
        'items'    => 'array',
    ];
}
