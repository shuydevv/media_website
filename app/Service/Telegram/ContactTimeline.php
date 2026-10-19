<?php

namespace App\Service\Telegram;

use App\Models\SiteLead;
use App\Models\TelegramContact;
use App\Models\TelegramMessage;
use Carbon\CarbonInterface;

/**
 * История человека в воронке для карточки в админке
 * (/admin/telegram/contacts/{id}): что он делал (по отметкам времени на
 * контакте), что ему ушло и что написал он сам (telegram_messages), заявки
 * с сайта — и что бот отправит дальше. Только читает.
 */
final class ContactTimeline
{
    /**
     * kind: action — сделал человек; user — написал; bot — ушло от бота;
     * admin — ответ из темы; planned — запланировано.
     *
     * @return array<int, array{at: CarbonInterface, icon: string, text: string, detail: ?string, kind: string}>
     */
    public static function build(TelegramContact $contact): array
    {
        $events = [];
        $add = function (?CarbonInterface $at, string $icon, string $text, string $kind = 'action', ?string $detail = null) use (&$events) {
            if ($at !== null) {
                $events[] = compact('at', 'icon', 'text', 'detail', 'kind');
            }
        };

        $add($contact->started_at, '▶️', 'Запустил бота', 'action', $contact->source ? 'метка '.$contact->source : 'без метки');
        if ($contact->last_start_at && $contact->last_start_at->gt($contact->started_at->copy()->addMinute())) {
            $add($contact->last_start_at, '🔁', 'Последний повторный /start');
        }

        foreach ([false, true] as $bonus) {
            $title = '«'.FunnelLessons::title($bonus ? FunnelLessons::bonusFor($contact->lesson) : $contact->lesson).'»';
            $prefix = $bonus ? 'бонусный урок ' : 'урок ';

            $add($contact->slot($bonus, 'opened_at'), '👀', 'Открыл '.$prefix.$title);

            $progress = (int) $contact->slot($bonus, 'progress');
            if ($progress > 0 && $contact->slot($bonus, 'last_watch_at')) {
                $add($contact->slot($bonus, 'last_watch_at'), '⏸', 'Смотрел '.$prefix.$title, 'action',
                    "посмотрел {$progress}%, остановился на ".self::clock((int) $contact->slot($bonus, 'position')));
            }
            $add($contact->slot($bonus, 'watched_at'), '✅', 'Досмотрел '.$prefix.$title.' до 80%');

            if ($rating = $contact->slot($bonus, 'rating')) {
                $add($contact->slot($bonus, 'rated_at'), '⭐', 'Оценил '.$prefix.$title.': '.FunnelTexts::ratingLabel($rating), 'action',
                    $contact->slot($bonus, 'rating_source') === 'site' ? 'на сайте, под видео' : 'в боте');
            }
        }

        foreach (SiteLead::where('telegram_contact_id', $contact->id)->get() as $lead) {
            $add($lead->created_at, '📝', 'Оставил заявку с сайта', 'action', $lead->cta_label);
        }

        $add($contact->blocked_at, '⛔', 'Заблокировал бота');

        $labels = collect(config('telegram_funnel.texts'))->map(fn ($t) => $t['label']);
        foreach ($contact->messages()->orderBy('id')->get() as $m) {
            $preview = $m->text !== null ? mb_strimwidth(trim($m->text), 0, 160, '…') : null;
            $media = $m->media_type ? '['.$m->media_type.'] ' : '';

            match ($m->kind) {
                TelegramMessage::KIND_USER => $add($m->created_at, '💬', 'Написал', 'user', $media.$preview),
                TelegramMessage::KIND_ADMIN => $add($m->created_at, '👤', 'Вы ответили', 'admin', $media.$preview),
                default => $add($m->created_at, '🤖', 'Бот: '.($labels[$m->step] ?? $m->step ?? 'сообщение'), 'bot', $preview),
            };
        }

        usort($events, fn ($a, $b) => $a['at'] <=> $b['at']);

        // Запланированное — в конце, по времени.
        $planned = [];
        foreach (TelegramContact::SCHEDULE_LABELS as $field => $label) {
            if ($contact->{$field}) {
                $planned[] = ['at' => $contact->{$field}, 'icon' => '⏰', 'text' => 'Запланировано: '.$label,
                    'detail' => 'уйдёт, если к этому времени ничего не изменится (не напишет сам, не оценит и т.п.)', 'kind' => 'planned'];
            }
        }
        usort($planned, fn ($a, $b) => $a['at'] <=> $b['at']);

        return array_merge($events, $planned);
    }

    private static function clock(int $seconds): string
    {
        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
