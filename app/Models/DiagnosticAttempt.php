<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Прохождение диагностики (App\Service\Diagnostic\DiagnosticRunner). См.
 * миграцию create_diagnostic_tables.
 */
class DiagnosticAttempt extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_FINISHED = 'finished';

    /** Начал заново посреди теста — это прохождение брошено (DiagnosticRunner::restart). */
    public const STATUS_ABANDONED = 'abandoned';

    protected $guarded = ['id'];

    protected $casts = [
        'block_order' => 'array',
        'levels' => 'array',
        'levels_low' => 'array',
        'result' => 'array',
        'target_set_at' => 'datetime',
        'improve_at' => 'datetime',
        'started_at' => 'datetime',
        'last_answer_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(TelegramContact::class, 'telegram_contact_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(DiagnosticAnswer::class);
    }

    public function isFinished(): bool
    {
        return $this->status === self::STATUS_FINISHED;
    }

    public function currentBlock(): ?string
    {
        return $this->block_order[$this->block_index] ?? null;
    }
}
