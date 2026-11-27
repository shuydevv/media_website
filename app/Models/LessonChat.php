<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Состояние чата трансляции урока (одна строка на урок, заводится лениво —
 * App\Service\LiveChat\LiveChatService::chatFor()). version — счётчик
 * изменений, см. миграцию create_live_chat_tables.
 */
class LessonChat extends Model
{
    protected $fillable = ['lesson_id', 'version', 'shift_seconds', 'pinned_message_id', 'closed_at'];

    protected $casts = [
        'closed_at' => 'datetime',
    ];

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }
}
