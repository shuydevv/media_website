<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseUser extends Model
{
    protected $table = 'course_user';
    protected $guarded = ['id'];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'expires_at' => 'datetime',
        'next_payment_due_at' => 'datetime',
        'promised_payment_expires_at' => 'datetime',
        'promised_payment_used_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'overdue_notified_at' => 'datetime',
        'promise_expiring_notified_at' => 'datetime',
        'autopay_enabled' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function promoCode()
    {
        return $this->belongsTo(PromoCode::class);
    }
}
