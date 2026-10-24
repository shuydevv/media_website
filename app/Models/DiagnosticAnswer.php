<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Один ответ в прохождении диагностики. См. миграцию create_diagnostic_tables. */
class DiagnosticAnswer extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = [
        'answer' => 'array',
        'correct' => 'boolean',
        'guess' => 'boolean',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(DiagnosticAttempt::class, 'diagnostic_attempt_id');
    }
}
