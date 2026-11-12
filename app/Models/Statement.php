<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Суждение тренажёра «Верные суждения». Заливается из
 * docs/statements/statements.json (php artisan statements:import),
 * руками в таблице не правится. Ученику выдаются только is_active.
 */
class Statement extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUSES = ['active', 'no_explanation', 'check', 'conflict'];

    protected $fillable = [
        'key', 'block', 'about', 'text', 'is_true', 'explanation', 'correction',
        'status', 'issue', 'is_active', 'source_task_id', 'source_line', 'theme', 'hash',
    ];

    protected $casts = [
        'is_true' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        // Демо-ученику — только лёгкий набор из config/demo.php.
        return \App\Service\Demo\DemoMode::restrict($query->where('is_active', true), 'statements', 'key');
    }
}
