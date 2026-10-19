<?php

namespace App\Jobs\Telegram;

use App\Models\SiteLead;
use App\Models\TelegramContact;
use App\Service\Telegram\AdminTopics;
use App\Service\Telegram\FunnelTexts;
use App\Service\Telegram\TelegramApi;
use App\Service\Telegram\TelegramApiException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Заявка с сайта → тема «📝 Заявки с сайта» в админ-группе, со звуком.
 * Повторы — у очереди: заявка уже лежит в site_leads, а sent_at = null
 * значит, что в группу она ещё не дошла.
 */
class SendSiteLead implements ShouldQueue
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
        $lead = SiteLead::find($this->leadId);
        if (! $lead || $lead->sent_at) {
            return;
        }

        $lines = [
            '📝 <b>Заявка с сайта</b>',
            'Имя: '.e($lead->name),
        ];
        if ($lead->telegram) {
            $lines[] = 'Telegram: <a href="'.e($lead->telegramUrl()).'">@'.e($lead->telegram).'</a>';
        } elseif (! $lead->phone && $lead->contact) {
            $lines[] = 'Telegram: <a href="'.e($lead->telegramUrl()).'">'.e($lead->contact->displayName()).'</a> (без логина)';
        } else {
            // t.me/+7… откроет чат, только если человек не скрыл номер
            // в настройках приватности Telegram.
            $lines[] = 'Телефон: <code>'.e($lead->phone).'</code> · <a href="'.e($lead->telegramUrl()).'">открыть в Telegram</a>';
        }
        if ($lead->cta_label) {
            $lines[] = 'Кнопка: '.e($lead->cta_label);
        }
        if ($lead->page) {
            $lines[] = 'Страница: '.e(preg_replace('~^https?://[^/]+~', '', $lead->page) ?: '/');
        }

        // Если этот человек уже запускал бота — сразу видно, откуда он и
        // смотрел ли пробный урок.
        $contact = $lead->contact ?? ($lead->telegram ? TelegramContact::where('username', $lead->telegram)->first() : null);
        if ($contact) {
            $lines[] = '';
            $lines[] = '🤖 Уже в боте с '.$contact->started_at->format('d.m')
                .($contact->source ? ', метка <code>'.e($contact->source).'</code>' : '')
                .($contact->opened_lesson_at ? ', урок открывал' : ', урок не открывал')
                .($contact->rating ? ', оценка '.e(FunnelTexts::ratingLabel($contact->rating)) : '');
        }

        try {
            // Записался со страницы урока, придя из бота: тема с ним уже
            // открыта и прозвонила (SiteLeadController ставит её в очередь
            // раньше этой задачи) — здесь только отметка, без звука.
            $silent = $lead->contact?->topic_id !== null;
            if ($silent) {
                $lines[] = '💬 Тема с ним открыта — отвечайте там';
            }
            (new AdminTopics(TelegramApi::funnel()))->postToService('leads', implode("\n", $lines), $silent);
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
