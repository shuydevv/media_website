<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Факт о событии или правлении — из «Супершпоры» автора курса. Текст не
 * переписывается без согласования (см. docs/history/).
 */
class HistFact extends Model
{
    protected $fillable = ['event_id', 'key', 'position', 'type', 'text', 'group', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function event()
    {
        return $this->belongsTo(HistEvent::class, 'event_id');
    }
}
