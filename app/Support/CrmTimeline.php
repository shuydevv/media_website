<?php

namespace App\Support;

use App\Models\CrmEvent;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Лента «История» в карточке ученика CRM: платежи (из payments, как есть)
 * + события CRM (crm_events: статус, «Напомнил», отказ, напоминания…),
 * новые сверху. Ждёт загруженные payments, courses и crmEvents.course.
 */
class CrmTimeline
{
    /** @return array<int, array{at: Carbon, text: string, tone: string}> */
    public static function for(User $student): array
    {
        $titles = $student->courses->pluck('title', 'id');
        $items = [];

        foreach ($student->payments as $payment) {
            if (! $payment->paid_at) {
                continue;
            }
            $items[] = [
                'at' => $payment->paid_at,
                'text' => self::paymentText($payment).self::courseSuffix($titles->get($payment->course_id)),
                'tone' => $payment->amount_cents > 0 && ! $payment->is_promise ? 'money' : 'plain',
            ];
        }

        foreach ($student->crmEvents as $event) {
            $items[] = [
                'at' => $event->created_at,
                'text' => $event->text.self::courseSuffix($event->course?->title),
                'tone' => $event->type === 'lost' ? 'bad' : 'plain',
            ];
        }

        usort($items, fn ($a, $b) => $b['at'] <=> $a['at']);

        return $items;
    }

    private static function paymentText(Payment $payment): string
    {
        if ($payment->is_promise || $payment->method === 'promised') {
            return 'Обещанный платёж';
        }
        if ($payment->method === 'promo') {
            return 'Доступ по промокоду';
        }
        if ($payment->amount_cents > 0) {
            return 'Оплата '.number_format($payment->amount_cents / 100, 0, ',', ' ').' ₽';
        }
        if ($payment->note && preg_match('/продлён(?: вручную)? до (\d{2}\.\d{2}\.\d{4})/u', $payment->note, $m)) {
            return 'Доступ продлён до '.$m[1];
        }
        if ($payment->note && str_contains($payment->note, 'пакет')) {
            return 'Продлён по пакету (0 ₽)';
        }

        return 'Продление без оплаты';
    }

    private static function courseSuffix(?string $title): string
    {
        return $title ? ' · '.$title : '';
    }
}
