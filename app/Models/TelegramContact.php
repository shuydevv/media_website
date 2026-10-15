<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Человек, запустивший бота воронки. См. миграции create_telegram_funnel_tables,
 * add_watch_and_three_level_rating_to_telegram_contacts и App\Service\Telegram\FunnelBot.
 *
 * У контакта два урока — «слота»: основной (lesson, на который пришёл из
 * рекламы) и бонусный. У каждого своя оценка и свой учёт просмотра; имена
 * полей слота — slotFields().
 */
class TelegramContact extends Model
{
    public const RATING_GREAT = 'great';

    public const RATING_GOOD = 'good';

    public const RATING_BAD = 'bad';

    public const RATINGS = [self::RATING_GREAT, self::RATING_GOOD, self::RATING_BAD];

    protected $guarded = ['id'];

    protected $casts = [
        'rated_at' => 'datetime',
        'bonus_rated_at' => 'datetime',
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
        'lesson_last_watch_at' => 'datetime',
        'bonus_last_watch_at' => 'datetime',
        'lesson_watched_at' => 'datetime',
        'bonus_watched_at' => 'datetime',
        'lesson_ask_due_at' => 'datetime',
        'bonus_ask_due_at' => 'datetime',
        'lesson_ask_sent_at' => 'datetime',
        'bonus_ask_sent_at' => 'datetime',
        'nudge_due_at' => 'datetime',
        'nudge_sent_at' => 'datetime',
        'question_due_at' => 'datetime',
        'question_sent_at' => 'datetime',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(TelegramMessage::class);
    }

    /**
     * Имена полей слота: основной урок ($bonus = false) или бонусный.
     *
     * @return array<string, string>
     */
    public static function slotFields(bool $bonus): array
    {
        $p = $bonus ? 'bonus' : 'lesson';

        return [
            'rating' => $bonus ? 'bonus_rating' : 'rating',
            'rated_at' => $bonus ? 'bonus_rated_at' : 'rated_at',
            'rating_source' => $bonus ? 'bonus_rating_source' : 'rating_source',
            'message_id' => $bonus ? 'bonus_rating_message_id' : 'rating_message_id',
            'opened_at' => $bonus ? 'opened_bonus_at' : 'opened_lesson_at',
            'watch_seconds' => "{$p}_watch_seconds",
            'duration' => "{$p}_duration",
            'position' => "{$p}_position",
            'progress' => "{$p}_progress",
            'last_watch_at' => "{$p}_last_watch_at",
            'watched_at' => "{$p}_watched_at",
            'ask_due_at' => "{$p}_ask_due_at",
            'ask_sent_at' => "{$p}_ask_sent_at",
        ];
    }

    public function slot(bool $bonus, string $field): mixed
    {
        return $this->{self::slotFields($bonus)[$field]};
    }

    public static function isGoodRating(?string $rating): bool
    {
        return $rating === self::RATING_GREAT || $rating === self::RATING_GOOD;
    }

    /** Хотя бы один урок досмотрен до порога — для нас это полноценный просмотр. */
    public function watchedAny(): bool
    {
        return $this->lesson_watched_at !== null || $this->bonus_watched_at !== null;
    }

    public function ratedAny(): bool
    {
        return $this->rating !== null || $this->bonus_rating !== null;
    }

    /**
     * Можно ли слать автосообщения-напоминания. Как только человек оценил
     * любой урок, написал сам или ему ответил админ — дальше ведёт живой
     * человек, таймеры молча не срабатывают.
     */
    public function automationAllowed(): bool
    {
        return $this->blocked_at === null
            && ! $this->ratedAny()
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
            self::isGoodRating($this->rating) || self::isGoodRating($this->bonus_rating) => '👍',
            $this->ratedAny() => '👎',
            $this->watchedAny() => '▶️',
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
