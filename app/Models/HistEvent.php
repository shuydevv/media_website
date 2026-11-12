<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Элемент тренажёра дат: событие или правление (kind). Наполняется только
 * импортом docs/history/events_base.json (App\Service\Dates\HistImporter) —
 * руками в базе не правится, следующая заливка перезапишет.
 */
class HistEvent extends Model
{
    public const KIND_EVENT = 'event';

    public const KIND_REIGN = 'reign';

    /** Уровни сложности — поле level; пока в базе только базовый. */
    public const LEVELS = [
        1 => 'Базовый',
        2 => 'Продвинутый',
        3 => 'Эксперт',
    ];

    protected $fillable = [
        'slug', 'kind', 'title', 'other_names', 'summary', 'date_display', 'year_start', 'year_end',
        'era', 'level', 'sort_order', 'parent_id', 'pair_notes', 'image', 'is_active',
    ];

    protected $casts = [
        'other_names' => 'array',
        'pair_notes' => 'array',
        'is_active' => 'boolean',
    ];

    public function facts()
    {
        return $this->hasMany(HistFact::class, 'event_id')->where('is_active', true)->orderBy('position');
    }

    public function confusables()
    {
        return $this->belongsToMany(self::class, 'hist_event_confusables', 'event_id', 'confusable_id');
    }

    public function reigns()
    {
        return $this->belongsToMany(self::class, 'hist_event_reigns', 'event_id', 'reign_id');
    }

    public function scopeActive($query)
    {
        // Демо-ученику — только лёгкий набор из config/demo.php.
        return \App\Service\Demo\DemoMode::restrict($query->where('is_active', true), 'dates', 'slug');
    }

    public function isReign(): bool
    {
        return $this->kind === self::KIND_REIGN;
    }

    /** Есть ли у элемента год окончания, который нужно вводить. */
    public function isRange(): bool
    {
        return $this->year_end !== null && (int) $this->year_end !== (int) $this->year_start;
    }

    /** Годы так, как их вводит ученик: «1709», «1700–1721», «с 2012». */
    public function yearsLabel(): string
    {
        if ($this->year_end === null && $this->isReign()) {
            return 'с '.$this->year_start;
        }

        return $this->isRange() ? $this->year_start.'–'.$this->year_end : (string) $this->year_start;
    }
}
