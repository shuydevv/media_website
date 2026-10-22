<?php

namespace App\Jobs\Telegram;

use App\Models\InboundLead;
use App\Service\Telegram\AdminTopics;
use App\Service\Telegram\LeadContact;
use App\Service\Telegram\TelegramApi;
use App\Service\Telegram\TelegramApiException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Заявка с другого сайта → тема этого сайта в админ-группе, со звуком.
 * Повторы — как у SendSiteLead: заявка уже в inbound_leads, sent_at = null
 * значит, что в группу она ещё не дошла.
 */
class SendInboundLead implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    public function __construct(public int $leadId)
    {
    }

    public function backoff(): array
    {
        return [10, 60, 300, 1800];
    }

    public function handle(): void
    {
        $lead = InboundLead::find($this->leadId);
        if (! $lead || $lead->sent_at) {
            return;
        }

        $lines = [
            '📝 <b>Заявка</b>',
            'Имя: '.e($lead->name),
        ];
        if ($lead->method) {
            $lines[] = 'Способ связи: '.e($lead->method);
        }

        // Похоже на логин или номер — даём ссылку; нет — как прислали.
        $parsed = LeadContact::parse($lead->contact);
        $lines[] = match (true) {
            $parsed === null => 'Контакт: '.e($lead->contact),
            $parsed['telegram'] !== null => 'Контакт: <a href="https://t.me/'.e($parsed['telegram']).'">@'.e($parsed['telegram']).'</a>',
            default => 'Контакт: <code>'.e($parsed['phone']).'</code>',
        };
        if ($lead->form) {
            $lines[] = 'Форма: '.e($lead->form);
        }

        try {
            (new AdminTopics(TelegramApi::funnel()))
                ->postToService($lead->topicKey(), implode("\n", $lines), false, $lead->topicName());
        } catch (TelegramApiException $e) {
            if ($e->isRateLimited() && $e->retryAfter) {
                $this->release($e->retryAfter + 1);

                return;
            }
            throw $e;
        }

        $lead->forceFill(['sent_at' => now()])->save();
    }
}
