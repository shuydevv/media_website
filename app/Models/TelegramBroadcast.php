<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Рассылка из темы «📣 Рассылка» админ-группы — см. миграцию
 * create_telegram_broadcasts_tables и App\Service\Telegram\Broadcast\BroadcastDesk.
 *
 * Статусы: draft → (scheduled →) sending → done; из черновика и плана —
 * cancelled, из отправки — stopped (кнопкой или сбоем, причина — stop_reason).
 */
class TelegramBroadcast extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_SENDING = 'sending';

    public const STATUS_DONE = 'done';

    public const STATUS_STOPPED = 'stopped';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Черновик',
        self::STATUS_SCHEDULED => 'Запланирована',
        self::STATUS_SENDING => 'Отправляется',
        self::STATUS_DONE => 'Отправлена',
        self::STATUS_STOPPED => 'Остановлена',
        self::STATUS_CANCELLED => 'Отменена',
    ];

    /** Кому: оба бота или один (App\Service\Telegram\TelegramBots). */
    public const AUDIENCE_ALL = 'all';

    public const AUDIENCE_LABELS = [
        self::AUDIENCE_ALL => 'оба бота',
        'lesson' => 'бот пробного урока',
        'diag' => 'бот диагностики',
    ];

    protected $guarded = ['id'];

    /** Те же умолчания, что в миграции, — чтобы были и у только что созданной модели. */
    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'audience' => self::AUDIENCE_ALL,
        'card_state' => 'main',
        'media_count' => 0,
    ];

    protected $casts = [
        'source_message_ids' => 'array',
        'excluded_user_ids' => 'array',
        'pending_at' => 'datetime',
        'card_refreshed_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'progress_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function recipients(): HasMany
    {
        return $this->hasMany(TelegramBroadcastRecipient::class);
    }

    /** Получатели ещё не зафиксированы — список считается заново. */
    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SCHEDULED], true);
    }

    /** @return int[] */
    public function excludedUserIds(): array
    {
        return array_map('intval', $this->excluded_user_ids ?? []);
    }

    public function isExcludedHere(int $telegramUserId): bool
    {
        return in_array($telegramUserId, $this->excludedUserIds(), true);
    }

    /** «фото», «3 видео», «альбом из 4»; пусто — только текст. */
    public function mediaLabel(): string
    {
        return match ($this->media_type) {
            null => '',
            'photo' => $this->media_count > 1 ? "{$this->media_count} фото" : 'фото',
            'video' => $this->media_count > 1 ? "{$this->media_count} видео" : 'видео',
            default => $this->media_count > 1 ? "альбом из {$this->media_count}" : $this->media_type,
        };
    }

    /** Короткое начало текста для карточки и списков. */
    public function preview(int $length = 80): string
    {
        $text = trim(preg_replace('~\s+~u', ' ', (string) $this->text));
        $media = $this->mediaLabel();

        if ($text === '') {
            return $media !== '' ? "[{$media}]" : '[без текста]';
        }

        return '«'.mb_strimwidth($text, 0, $length, '…').'»'.($media !== '' ? " + {$media}" : '');
    }
}
