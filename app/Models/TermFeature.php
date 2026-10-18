<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Признак термина. Текст — формулировка из критериев ЕГЭ, не переписывается
 * (см. docs/terms/, раздел «Формулировки признаков»).
 */
class TermFeature extends Model
{
    protected $fillable = ['term_id', 'key', 'position', 'text', 'group', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function term()
    {
        return $this->belongsTo(Term::class);
    }
}
