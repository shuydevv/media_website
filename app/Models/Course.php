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

    public function sessions() {
        return $this->hasMany(CourseSession::class);
    }

    protected static function booted()
{
    static::deleting(function ($course) {
        $course->scheduleTemplates()->delete();
        $course->sessions()->delete();
    });
}
}
