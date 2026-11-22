<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * План тренажёров ученика на один день — см. App\Service\Trainers\DailyPlanService.
 */
class DailyPlan extends Model
{
    protected $fillable = ['user_id', 'day', 'minutes', 'light', 'items', 'stale', 'completed_at'];

    protected $casts = [
        'day' => 'date',
        'items' => 'array',
        'stale' => 'boolean',
        'completed_at' => 'datetime',
    ];
}
