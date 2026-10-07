<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * День, в который ученик был на платформе — см. TrackStudentActivity.
 */
class UserActivityDay extends Model
{
    /** Длина одного отрезка активности в минутах (active_slots считается в них). */
    public const SLOT_MINUTES = 5;

    public $timestamps = false;

    protected $fillable = ['user_id', 'date', 'active_slots', 'first_seen_at', 'last_seen_at'];

    protected $casts = [
        'date' => 'date',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function minutes(): int
    {
        return (int) $this->active_slots * self::SLOT_MINUTES;
    }
}
