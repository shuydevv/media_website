<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoRedemption extends Model
{
    protected $fillable = [
        'promo_code_id','user_id','course_id','enrolled_at','expires_at'
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'expires_at'  => 'datetime',
    ];

    public function promo()
    {
        return $this->belongsTo(\App\Models\PromoCode::class, 'promo_code_id');
    }
}
