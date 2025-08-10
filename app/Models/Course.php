<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'courses';
    protected $guarded = false;


    public function scheduleTemplates()
    {
        return $this->hasMany(CourseScheduleTemplate::class);
    }

    public function image() {
        return $this->hasMany(Image::class);
    }

    public function students()
    {
        return $this->belongsToMany(\App\Models\User::class, 'course_user')
            ->withPivot(['status','enrolled_at','expires_at','source','payment_id','promo_code'])
            ->withTimestamps();
    }


    public function lessons()
    {
        return $this->hasManyThrough(
            \App\Models\Lesson::class,
            \App\Models\CourseSession::class,
            'course_id',          // FK в course_sessions
            'course_session_id',  // FK в lessons
            'id',                 // локальный ключ Course
            'id'                  // локальный ключ CourseSession
        );
    }

    public function sessions() {
        return $this->hasMany(CourseSession::class);
    }
    public function category() {
        return $this->belongsTo(Category::class);
    }

    protected static function booted()
{
    static::deleting(function ($course) {
        $course->scheduleTemplates()->delete();
        $course->sessions()->delete();
    });
}
}
