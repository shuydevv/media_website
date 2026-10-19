<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Заявка из формы «Записаться» на сайте — см. SiteLeadController и
 * миграцию create_site_leads_and_service_topics_tables.
 */
class SiteLead extends Model
{
    protected $fillable = ['name', 'telegram', 'phone', 'cta', 'cta_label', 'page', 'token', 'telegram_contact_id', 'sent_at'];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    /** Ссылка «Получить ответ в Telegram» со страницы «Спасибо». */
    public function botStartUrl(): string
    {
        return 'https://t.me/'.config('telegram_funnel.bot_username').'?start=lead_'.$this->token;
    }

    /** Контакт бота: пришёл на сайт из бота (cookie) или потом запустил бота по ссылке из заявки. */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(TelegramContact::class, 'telegram_contact_id');
    }

    /** Ссылка на чат в Telegram: по логину, по номеру (если номер не скрыт) или по id из бота. */
    public function telegramUrl(): string
    {
        if ($this->telegram || $this->phone) {
            return 'https://t.me/'.($this->telegram ?? $this->phone);
        }

        return 'tg://user?id='.($this->contact?->telegram_user_id ?? '');
    }

    public function contactLabel(): string
    {
        return match (true) {
            (bool) $this->telegram => '@'.$this->telegram,
            (bool) $this->phone => (string) $this->phone,
            default => $this->contact?->displayName() ?? '—',
        };
    }
}
