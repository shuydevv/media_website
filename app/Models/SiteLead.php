<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    /** Ссылка на чат в Telegram: по логину или по номеру (если номер не скрыт). */
    public function telegramUrl(): string
    {
        return 'https://t.me/'.($this->telegram ?? $this->phone);
    }

    public function contactLabel(): string
    {
        return $this->telegram ? '@'.$this->telegram : (string) $this->phone;
    }
}
