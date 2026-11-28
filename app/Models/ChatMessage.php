<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Сообщение чата трансляции. author_name/author_badge — снимок на момент
 * отправки: сменил ученик имя — старые сообщения остаются под старым.
 * deleted_at — не SoftDeletes: удалённое админом остаётся в базе для споров,
 * но браузеры получают его как «удалено» и убирают из ленты.
 */
class ChatMessage extends Model
{
    protected $fillable = [
        'lesson_id', 'user_id', 'author_name', 'author_badge', 'body', 'card', 'reply_to_id', 'thread_id',
        'is_question', 'answered_at', 'answered_by', 'deleted_at', 'deleted_by', 'version',
    ];

    protected $casts = [
        'is_question' => 'boolean',
        'card' => 'array', // итоги урока — карточка вместо текста
        'answered_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function replyTo()
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }
}
