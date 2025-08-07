<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    use HasFactory;

protected $fillable = [
    'course_session_id',
    'title',
    'description',
    'meet_link',
    'recording_link',
    'notes_link',
    'image',
];

    public function session()
    {
        return $this->belongsTo(CourseSession::class, 'course_session_id');
    }

}

