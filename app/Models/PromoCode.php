<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoCode extends Model
{
    // used_count намеренно не в $fillable — это внутренний счётчик,
    // изменяемый только через increment() в BillingService/RedeemController
    // под lockForUpdate(), а не через формы/mass-assignment.
    protected $fillable = [
        'code','course_id','duration_days','starts_at','ends_at','max_uses','is_active',
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
