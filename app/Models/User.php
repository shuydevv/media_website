<?php

namespace App\Models;

use App\Notifications\SendVerifyWithQueueNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;
    // SoftDeletes;

    const ROLE_ADMIN  = 1;
    const ROLE_READER = 2;
    const ROLE_MENTOR = 3;

    public static function getRoles()
    {
        return [
            self::ROLE_ADMIN  => 'Админ',
            self::ROLE_READER => 'Пользователь',
            self::ROLE_MENTOR => 'Куратор',
        ];
    }

    public function courses()
    {
        return $this->belongsToMany(\App\Models\Course::class, 'course_user')
            ->withPivot([
                'status', 'enrolled_at', 'expires_at', 'source', 'payment_id', 'promo_code',
                'billing_interval_days', 'next_payment_due_at', 'autopay_enabled',
                'promised_payment_expires_at', 'promised_payment_used_at', 'reminder_sent_at',
            ])
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

    public function submissions()
    {
        return $this->hasMany(\App\Models\Submission::class);
    }

    /**
     * Проверка: админ.
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Проверка: куратор.
     */
    public function isMentor(): bool
    {
        return $this->role === self::ROLE_MENTOR;
    }

    /**
     * Проверка: студент/обычный пользователь.
     */
    public function isStudent(): bool
    {
        return $this->role === self::ROLE_READER;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'first_name', 'last_name',
        'password',
        'role',
        'phone','phone_verified_at','timezone','locale',
        'fish_corm_balance', 'fish_total_fed', 'fish_streak_count', 'fish_last_active_date', 'fish_milestones',
        'fish_name', 'fish_background', 'fish_unlocked_backgrounds',
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
        'phone_verified_at' => 'datetime',
        'profile_completed_at' => 'datetime',
        'notification_preferences' => 'array',
        'fish_last_active_date' => 'date',
        'fish_milestones' => 'array',
        'fish_unlocked_backgrounds' => 'array',
    ];

    public function sendEmailVerificationNotification()
    {
        $this->notify(new SendVerifyWithQueueNotification());
    }

        public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Хочет ли пользователь получать уведомление данного типа (слаг —
     * см. App\Notifications\NotificationPreferenceRegistry). Отсутствие
     * ключа = включено по умолчанию (opt-out, а не opt-in) — так
     * существующим пользователям ничего не нужно мигрировать.
     */
    public function wantsNotification(string $slug): bool
    {
        return (bool) ($this->notification_preferences[$slug] ?? true);
    }

    /**
     * Тот же паттерн, что и Lesson::getImageUrlAttribute() — 'avatar'
     * хранит путь на диске public, отдаём готовый URL.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if (!$this->avatar) {
            return null;
        }

        if (Str::startsWith($this->avatar, ['http://', 'https://', '/storage/', 'data:'])) {
            return $this->avatar;
        }

        return Storage::url($this->avatar);
    }
}
