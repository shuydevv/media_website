<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Заявка с другого моего сайта — см. InboundLeadController и
 * config/inbound_leads.php.
 */
class InboundLead extends Model
{
    protected $fillable = ['site', 'name', 'method', 'contact', 'sent_at'];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    /** Ключ служебной темы в telegram_service_topics (не больше 32 символов). */
    public function topicKey(): string
    {
        return 'site_'.$this->site;
    }

    public function topicName(): string
    {
        return config("inbound_leads.sites.{$this->site}.topic") ?? '🌐 Заявки — '.$this->site;
    }
}
