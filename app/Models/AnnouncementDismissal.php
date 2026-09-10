<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnnouncementDismissal extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'announcement_id', 'user_id', 'dismissed_at',
    ];

    protected $casts = [
        'dismissed_at' => 'datetime',
    ];
}
