<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Термин тренажёра (задание 18). Наполняется только импортом JSON
 * (App\Service\Terms\TermImporter) — руками в базе не правится, следующая
 * заливка перезапишет. Механика — docs/terms/.
 */
class Term extends Model
{
    public const PRIORITY_MAIN = 1;

    public const PRIORITY_RARE = 2;

    public const PRIORITY_EXPERT = 3;

    /**
     * Уровни сложности — это поле priority. Изучаются по порядку: следующий
     * уровень открывается, когда в предыдущих не осталось неизученных.
     */
    public const LEVELS = [
        self::PRIORITY_MAIN => 'Базовый',
        self::PRIORITY_RARE => 'Продвинутый',
        self::PRIORITY_EXPERT => 'Эксперт',
    ];

    protected $fillable = [
        'slug', 'source_id', 'title', 'qualifier', 'other_names', 'pair_notes', 'image',
        'code', 'section', 'priority', 'sort_order', 'parent_id', 'is_active',
    ];

    protected $casts = [
        'other_names' => 'array',
        'pair_notes' => 'array',
        'is_active' => 'boolean',
    ];

    public function features()
    {
        return $this->hasMany(TermFeature::class)->where('is_active', true)->orderBy('position');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function confusables()
    {
        return $this->belongsToMany(self::class, 'term_confusables', 'term_id', 'confusable_term_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Всё, что засчитывается как верный ответ в способе А.
     *
     * @return string[]
     */
    public function acceptedNames(): array
    {
        return array_merge([$this->title], $this->other_names ?? []);
    }
}
