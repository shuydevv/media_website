<?php

namespace App\Models;

use App\Service\Telegram\Broadcast\BroadcastSender;
use App\Service\Telegram\TelegramBots;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Человек, запустивший одного из ботов (поле bot — App\Service\Telegram\TelegramBots;
 * в двух ботах — два контакта). Почти все поля ниже — воронки пробного урока
 * (bot = lesson). См. миграции create_telegram_funnel_tables,
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
        'awaiting_alerted_at' => 'datetime',
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
        'followup_due_at' => 'datetime',
        'lesson_player_at' => 'datetime',
        'bonus_player_at' => 'datetime',
        'lesson_played_at' => 'datetime',
        'bonus_played_at' => 'datetime',
        'followup_sent_at' => 'datetime',
        'broadcast_opt_out_at' => 'datetime',
        'last_broadcast_at' => 'datetime',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(TelegramMessage::class);
    }

    /** Прохождения диагностики — у контактов бота diag. */
    public function diagnosticAttempts(): HasMany
    {
        return $this->hasMany(DiagnosticAttempt::class);
    }

    public function latestDiagnostic(): ?DiagnosticAttempt
    {
        return $this->diagnosticAttempts()->latest('id')->first();
    }

    /** Контакты одного бота (App\Service\Telegram\TelegramBots). */
    public function scopeForBot(Builder $q, string $bot): Builder
    {
        return $q->where('bot', $bot);
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
        if ($this->bot === TelegramBots::DIAG) {
            return $this->diagStatusEmoji();
        }

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

    /**
     * Статус в названии темы: statusEmoji() и 📣, пока человек ждёт ответа
     * на сообщение, написанное в ответ на рассылку (BroadcastSender::REPLY_WINDOW_DAYS),
     * — после рассылки таких тем появляется много разом. Хранится в
     * topic_status: сменился — тему переименовывают.
     */
    public function topicStatus(): string
    {
        return $this->statusEmoji().($this->repliedToBroadcast() && $this->awaitingReply() ? '📣' : '');
    }

    /** Последнее сообщение человека — в течение окна ответа после последней рассылки ему. */
    public function repliedToBroadcast(): bool
    {
        return $this->last_broadcast_at !== null && $this->last_user_message_at !== null
            && $this->last_user_message_at->gte($this->last_broadcast_at)
            && $this->last_user_message_at->lte($this->last_broadcast_at->copy()->addDays(BroadcastSender::REPLY_WINDOW_DAYS));
    }

    /** Статус контакта бота диагностики — по последнему прохождению. */
    private function diagStatusEmoji(): string
    {
        $attempt = $this->latestDiagnostic();

        return match (true) {
            $this->blocked_at !== null => '⛔',
            $this->awaitingReply() => '💬',
            $attempt?->improve === 'yes' => '🔥',
            $attempt?->improve === 'no' => '🙅',
            $attempt?->isFinished() === true => '✅',
            $attempt !== null => '▶️',
            default => '🆕',
        };
    }

    /**
     * Этапы воронки для списка в админке — взаимоисключающие, в порядке
     * «насколько далеко зашёл»: человек всегда ровно на одном.
     */
    public const STAGES = [
        'not_opened' => 'Не открыл урок',
        'opened' => 'Открыл, не досмотрел',
        'watched' => 'Досмотрел, не оценил',
        'rated' => 'Оценил',
        'wrote' => 'Написал',
        'blocked' => 'Заблокировал',
    ];

    /** То же условие, что stageKey(), — но запросом, для фильтра и счётчиков. */
    public function scopeStage(Builder $q, string $stage): Builder
    {
        $opened = fn (Builder $w) => $w->whereNotNull('opened_lesson_at')->orWhereNotNull('opened_bonus_at');
        $watched = fn (Builder $w) => $w->whereNotNull('lesson_watched_at')->orWhereNotNull('bonus_watched_at');
        $rated = fn (Builder $w) => $w->whereNotNull('rating')->orWhereNotNull('bonus_rating');
        $notWrote = fn (Builder $w) => $w->whereNull('blocked_at')->whereNull('first_user_message_at');

        return match ($stage) {
            'blocked' => $q->whereNotNull('blocked_at'),
            'wrote' => $q->whereNull('blocked_at')->whereNotNull('first_user_message_at'),
            'rated' => $notWrote($q)->where($rated),
            'watched' => $notWrote($q)->whereNot($rated)->where($watched),
            'opened' => $notWrote($q)->whereNot($rated)->whereNot($watched)->where($opened),
            'not_opened' => $notWrote($q)->whereNot($rated)->whereNot($watched)->whereNot($opened),
            default => $q,
        };
    }

    public function stageKey(): string
    {
        return match (true) {
            $this->blocked_at !== null => 'blocked',
            $this->first_user_message_at !== null => 'wrote',
            $this->ratedAny() => 'rated',
            $this->watchedAny() => 'watched',
            $this->opened_lesson_at !== null || $this->opened_bonus_at !== null => 'opened',
            default => 'not_opened',
        };
    }

    /** Отложенные сообщения бота диагностики (DiagBot::DUE_STEPS) — подписи для админки. */
    public const DIAG_SCHEDULE_LABELS = [
        'question_due_at' => 'вопрос от Александра после «Да, хочу»',
        'nudge_due_at' => 'напоминание «начал и бросил»',
        'reminder_due_at' => '«Получилось пройти диагностику?»',
        'followup_due_at' => 'второе касание',
    ];

    /** @return array<string, string> поле *_due_at → подпись, для бота этого контакта */
    public function scheduleLabels(): array
    {
        return $this->bot === TelegramBots::DIAG ? self::DIAG_SCHEDULE_LABELS : self::SCHEDULE_LABELS;
    }

    /** Отложенные сообщения (поля *_due_at, FunnelBot::DUE_STEPS) — подписи для админки. */
    public const SCHEDULE_LABELS = [
        'question_due_at' => 'вопрос от Александра после оценки',
        'lesson_ask_due_at' => '«Как вам урок?» — основной урок',
        'bonus_ask_due_at' => '«Как вам урок?» — бонусный урок',
        'nudge_due_at' => 'напоминание недосмотревшему',
        'reminder_due_at' => '«Получилось посмотреть урок?»',
        'followup_due_at' => 'второе касание — «какая тема даётся сложнее всего?»',
    ];

    /**
     * Ближайшее запланированное сообщение: [подпись, когда] или null. Уйдёт
     * ли оно на самом деле, решается в момент отправки (FunnelBot) — здесь
     * только то, что стоит в расписании.
     */
    public function nextScheduled(): ?array
    {
        $next = null;
        foreach ($this->scheduleLabels() as $field => $label) {
            if ($this->{$field} !== null && ($next === null || $this->{$field}->lt($next[1]))) {
                $next = [$label, $this->{$field}];
            }
        }

        return $next;
    }

    public function displayName(): string
    {
        $name = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));

        return $name !== '' ? $name : ($this->username ? '@'.$this->username : 'id'.$this->telegram_user_id);
    }
}
