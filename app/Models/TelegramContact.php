<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Человек, запустивший бота воронки. См. миграцию
 * create_telegram_funnel_tables и App\Service\Telegram\FunnelBot.
 */
class TelegramContact extends Model
{
    public const RATING_UP = 'up';

    public const RATING_DOWN = 'down';

    protected $fillable = [
        'telegram_user_id', 'username', 'first_name', 'last_name',
        'source', 'lesson', 'link_token',
        'topic_id', 'topic_status',
        'rating', 'rated_at', 'rating_message_id',
        'opened_lesson_at', 'opened_bonus_at',
        'reminder_due_at', 'reminder_sent_at',
        'first_user_message_at', 'last_user_message_at', 'user_messages_count',
        'first_admin_message_at', 'last_admin_message_at',
        'blocked_at', 'started_at', 'last_start_at',
    ];

    protected $casts = [
        'rated_at' => 'datetime',
        'opened_lesson_at' => 'datetime',
        'opened_bonus_at' => 'datetime',
        'reminder_due_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'first_user_message_at' => 'datetime',
        'last_user_message_at' => 'datetime',
        'first_admin_message_at' => 'datetime',
        'last_admin_message_at' => 'datetime',
        'blocked_at' => 'datetime',
        'started_at' => 'datetime',
        'last_start_at' => 'datetime',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(TelegramMessage::class);
    }

    /**
     * Можно ли ещё слать автосообщения воронки (напоминание). Как только
     * человек нажал кнопку, написал сам или ему ответил админ — дальше
     * ведёт живой человек, таймеры молча не срабатывают. После 👎 — стоп
     * навсегда (rating уже не null).
     */
    public function automationAllowed(): bool
    {
        return $this->blocked_at === null
            && $this->rating === null
            && $this->first_user_message_at === null
            && $this->first_admin_message_at === null;
    }

    /** Человек написал, а ответа после этого ещё не было. */
    public function awaitingReply(): bool
    {
        return $this->last_user_message_at !== null
            && ($this->last_admin_message_at === null || $this->last_user_message_at->gt($this->last_admin_message_at));
    }

    /**
     * Эмодзи в начале названия темы — чтобы по списку тем в админ-группе
     * было видно, кому надо ответить, без открытия каждой.
     */
    public function statusEmoji(): string
    {
        return match (true) {
            $this->blocked_at !== null => '⛔',
            $this->awaitingReply() => '💬',
            $this->rating === self::RATING_UP => '👍',
            $this->rating === self::RATING_DOWN => '👎',
            $this->opened_lesson_at !== null || $this->opened_bonus_at !== null => '👀',
            default => '🆕',
        };
    }

    public function displayName(): string
    {
        $name = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));

        return $name !== '' ? $name : ($this->username ? '@'.$this->username : 'id'.$this->telegram_user_id);
    }
}
