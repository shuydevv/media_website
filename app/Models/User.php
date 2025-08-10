<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Notifications\SendVerifyWithQueueNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;
    // SoftDeletes;

    const ROLE_ADMIN = 1;
    const ROLE_READER = 2;

    public static function getRoles() {
        return [
            self::ROLE_ADMIN => 'Админ',
            self::ROLE_READER => 'Пользователь',
        ];
    }

    public function courses()
    {
        return $this->belongsToMany(\App\Models\Course::class, 'course_user')
            ->withPivot(['status','enrolled_at','expires_at','source','payment_id','promo_code'])
            ->withTimestamps();
    }

    public function hasActiveEnrollment($course): bool
    {
        $courseId = is_object($course) ? $course->id : (int) $course;

        return $this->courses()
            ->wherePivot('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            })
            ->where('courses.id', $courseId)
            ->exists();
    }




    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function sendEmailVerificationNotification()
    {
        $this->notify(new SendVerifyWithQueueNotification());
    }
}
