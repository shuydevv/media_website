<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;

class CourseSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'date',
        'start_time',
        'end_time',
        'status',
        'duration_minutes',
    ];

    /**
     * Сессия принадлежит курсу
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Получить дату и время начала сессии как Carbon объект
     */
    public function startDateTime()
    {
        return Carbon::parse("{$this->date} {$this->start_time}");
    }

    /**
     * Получить дату и время окончания сессии как Carbon объект
     */
    public function endDateTime()
    {
        return Carbon::parse("{$this->date} {$this->end_time}");
    }
}
