<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Сколько ученик посмотрел урок — см. миграцию create_lesson_views_table и
 * Student\LessonWatchController.
 */
class LessonView extends Model
{
    public const KIND_RECORDING = 'recording';

    public const KIND_SHORT = 'short';

    public const KIND_LIVE = 'live';

    public const KIND_NOTES = 'notes';

    public const VIDEO_KINDS = [self::KIND_RECORDING, self::KIND_SHORT, self::KIND_LIVE];

    protected $fillable = [
        'user_id', 'lesson_id', 'kind',
        'watched_seconds', 'max_position_seconds', 'duration_seconds',
        'first_watched_at', 'last_watched_at',
    ];

    protected $casts = [
        'first_watched_at' => 'datetime',
        'last_watched_at' => 'datetime',
    ];

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * Доля просмотренного, 0–100. null — длительность неизвестна (эфир или
     * плеер её не сообщил). Пересмотр одного и того же места увеличивает
     * watched_seconds сверх длительности — поэтому срезаем до 100.
     */
    public function percent(): ?int
    {
        if (! $this->duration_seconds) {
            return null;
        }

        return (int) min(100, round($this->watched_seconds / $this->duration_seconds * 100));
    }
}
