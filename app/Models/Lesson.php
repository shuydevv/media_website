<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Homework;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\CourseSession;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Lesson extends Model
{
    use HasFactory;

protected $fillable = [
    'course_session_id',
    'title',
    'description',
    'meet_link',
    'recording_link',
    'short_class',
    'notes_link',
    'image',
];

    public function session()
    {
        return $this->belongsTo(CourseSession::class, 'course_session_id');
    }
    public function homework(): HasOne
    {
        return $this->hasOne(Homework::class);
    }

    public function courseSession(): BelongsTo
    {
        return $this->belongsTo(CourseSession::class, 'course_session_id');
    }

    public function getImageUrlAttribute(): ?string
    {
        $img = $this->image ?? null;
        if (!$img) {
            return null;
        }

        // Внешний URL или уже готовый путь — отдать как есть
        if (Str::startsWith($img, ['http://', 'https://', '/storage/', 'data:'])) {
            return $img;
        }

        // Путь из диска storage (public) -> /storage/...
        return Storage::url($img);
    }

}

