<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;


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
 * Показываем только «видимые» сессии:
 * - is_active = true (если колонка есть)
 * - is_canceled = false (если колонка есть)
 * - status НЕ в ['canceled','cancelled','отменено','отменена'] (если колонка есть)
 */
    public function scopeVisible($q)
    {
        $table = $this->getTable();

        if (Schema::hasColumn($table, 'is_active')) {
            $q->where('is_active', true);
        }
        if (Schema::hasColumn($table, 'is_canceled')) {
            $q->where('is_canceled', false);
        }
        if (Schema::hasColumn($table, 'status')) {
            $q->whereNotIn('status', ['canceled','cancelled','отменено','отменена']);
        }

        return $q;
    }

        public function lesson()
    {
        return $this->hasOne(Lesson::class, 'course_session_id');
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

// Всегда даём нормализованную дату/время начала сессии,
// подхватывая любое из возможных полей БД.
public function getStartDateTimeAttribute(): ?Carbon
{
    $candidates = [
        $this->start_at ?? null,
        $this->start_datetime ?? null,
        ($this->start_date ?? null) && ($this->start_time ?? null)
            ? ($this->start_date.' '.$this->start_time) : null,
        $this->date ?? null, // если дата с временем хранится в одном поле
    ];

    foreach ($candidates as $val) {
        if (!$val) continue;
        try {
            return $val instanceof Carbon ? $val : Carbon::parse($val);
        } catch (\Throwable $e) {
            // пробуем следующий вариант
        }
    }
    return null;
}

// Конец занятия на основе длительности (если указана)
public function getEndDateTimeAttribute(): ?Carbon
{
    $start = $this->start_date_time; // использует аксессор выше
    if (!$start) return null;

    $minutes = $this->duration_minutes ?? $this->duration ?? null;
    if (!$minutes) return null;

    return (clone $start)->addMinutes((int) $minutes);
}

public function getDisplayDateAttribute(): ?string
{
    if (!empty($this->start_at)) {
        try { return Carbon::parse($this->start_at)->format('d.m.Y'); } catch (\Throwable $e) {}
    }
    if (!empty($this->start_date)) {
        try { return Carbon::parse($this->start_date)->format('d.m.Y'); } catch (\Throwable $e) { return (string)$this->start_date; }
    }
    if (!empty($this->date)) {
        try { return Carbon::parse($this->date)->format('d.m.Y'); } catch (\Throwable $e) { return (string)$this->date; }
    }
    return null;
}

public function getDisplayTimeAttribute(): ?string
{
    if (!empty($this->start_at)) {
        try { return Carbon::parse($this->start_at)->format('H:i'); } catch (\Throwable $e) {}
    }
    if (!empty($this->start_time)) {
        return (string)$this->start_time; // уже строка «HH:MM»
    }
    if (!empty($this->time)) {
        return (string)$this->time; // уже строка «HH:MM»
    }
    return null;
}

/** Время окончания для карточки (по duration_minutes, если есть) — без смены TZ */
public function getDisplayEndTimeAttribute(): ?string
{
    // Сначала соберём «полный» старт
    $start = null;
    if (!empty($this->start_at)) {
        try { $start = Carbon::parse($this->start_at); } catch (\Throwable $e) { $start = null; }
    }
    if (!$start) {
        // Пытаемся из date + time (в любом из полей)
        $d = $this->start_date ?? $this->date ?? null;
        $t = $this->start_time ?? $this->time ?? null;
        if ($d && $t) {
            try { $start = Carbon::parse($d.' '.$t); } catch (\Throwable $e) { $start = null; }
        }
    }
    if (!$start) return null;

    $minutes = $this->duration_minutes ?? null;
    if (!$minutes) return null;

    return $start->copy()->addMinutes((int)$minutes)->format('H:i');
}

}
