<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Жалоба ученика из квиза тренажёра дат: верный факт и показанные неверные
 * варианты. Как TermReport.
 */
class HistReport extends Model
{
    protected $fillable = ['user_id', 'event_id', 'fact_id', 'distractor_ids', 'comment', 'resolved_at'];

    protected $casts = [
        'distractor_ids' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->belongsTo(HistEvent::class, 'event_id');
    }

    public function fact()
    {
        return $this->belongsTo(HistFact::class, 'fact_id');
    }
}
