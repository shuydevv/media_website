<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoCode extends Model
{
    protected $fillable = [
        'code','course_id','duration_days','starts_at','ends_at','max_uses','used_count','is_active',
        'kind','discount_mode','discount_value_cents','discount_percent','currency',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
        'is_active' => 'boolean',
    ];

    public function isAccess(): bool
    {
        return $this->kind === 'access';
    }

    public function isDiscount(): bool
    {
        return $this->kind === 'discount';
    }

    public function course()
    {
        return $this->belongsTo(\App\Models\Course::class);
    }
}
