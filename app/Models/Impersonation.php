<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Impersonation extends Model
{
    protected $fillable = [
        'admin_id',
        'user_id',
        'ip_address',
        'ended_at',
    ];

    protected $casts = [
        'ended_at' => 'datetime',
    ];

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
