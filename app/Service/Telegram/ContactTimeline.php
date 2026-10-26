<?php

namespace App\Service\Telegram;

use App\Models\SiteLead;
use App\Models\TelegramContact;
use App\Models\TelegramMessage;
use App\Service\Diagnostic\DiagnosticSummary;
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

        if ($contact->bot === TelegramBots::DIAG) {
            self::diagnosticEvents($contact, $add);
        }

        // Уроки — только у контактов бота воронки (у остальных lesson пуст).
        foreach ($contact->bot === TelegramBots::LESSON ? [false, true] : [] as $bonus) {
            $title = '«'.FunnelLessons::title($bonus ? FunnelLessons::bonusFor($contact->lesson) : $contact->lesson).'»';
            $prefix = $bonus ? 'бонусный урок ' : 'урок ';

            $add($contact->slot($bonus, 'opened_at'), '👀', 'Открыл '.$prefix.$title);

            $p = $bonus ? 'bonus' : 'lesson';
            if ($contact->{"{$p}_player_status"} === 'fallback') {
                $add($contact->{"{$p}_player_at"}, '⚠️', 'Плеер с учётом просмотра не загрузился — показан обычный', 'action',
                    'видео человек видел, но сколько посмотрел — не узнать');
            }
            $add($contact->{"{$p}_played_at"}, '▶️', 'Включил видео: '.$prefix.$title);

            $progress = (int) $contact->slot($bonus, 'progress');
            $watched = (int) $contact->slot($bonus, 'watch_seconds');
            if ($watched > 0 && $contact->slot($bonus, 'last_watch_at')) {
                $add($contact->slot($bonus, 'last_watch_at'), '⏸', 'Смотрел '.$prefix.$title, 'action',
                    'смотрел '.self::clock($watched).($progress > 0 ? " ({$progress}%)" : '')
                    .', остановился на '.self::clock((int) $contact->slot($bonus, 'position')));
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

        $labels = collect(FunnelTexts::definitions())->map(fn ($t) => $t['label']);
        foreach ($contact->messages()->orderBy('id')->get() as $m) {
            $preview = $m->text !== null ? mb_strimwidth(trim($m->text), 0, 160, '…') : null;
            $media = $m->media_type ? '['.$m->media_type.'] ' : '';

            match ($m->kind) {
                // Пару минут без отметки — норма (очередь); дольше — не дошло
                // до темы, досылает telegram:resend-unforwarded.
                TelegramMessage::KIND_USER => $add($m->created_at, '💬',
                    'Написал'.($m->forwarded_at === null && $m->created_at->lt(now()->subMinutes(3)) ? ' — ⚠️ не переслано в тему админ-группы' : ''),
                    'user', $media.$preview.($m->forwarded_at === null && $m->forward_error ? "\nошибка: ".$m->forward_error : '')),
                TelegramMessage::KIND_ADMIN => $add($m->created_at, '👤', 'Вы ответили', 'admin', $media.$preview),
                default => $add($m->created_at, '🤖', 'Бот: '.($labels[$m->step] ?? $m->step ?? 'сообщение'), 'bot', $preview),
            };
        }

        usort($events, fn ($a, $b) => $a['at'] <=> $b['at']);

        // Запланированное — в конце, по времени.
        $planned = [];
        foreach ($contact->scheduleLabels() as $field => $label) {
            if ($contact->{$field}) {
                $planned[] = ['at' => $contact->{$field}, 'icon' => '⏰', 'text' => 'Запланировано: '.$label,
                    'detail' => 'уйдёт, если к этому времени ничего не изменится (не напишет сам, не оценит и т.п.)', 'kind' => 'planned'];
            }
        }
        usort($planned, fn ($a, $b) => $a['at'] <=> $b['at']);

        return array_merge($events, $planned);
    }

    /** Прохождения диагностики: начал, прошёл (с баллом), ответ на «Хочешь улучшить?». */
    private static function diagnosticEvents(TelegramContact $contact, callable $add): void
    {
        foreach ($contact->diagnosticAttempts()->orderBy('id')->get() as $a) {
            $add($a->started_at, '🧪', 'Начал диагностику', 'action', $a->entry === 'miniapp' ? 'в Telegram' : null);
            if ($a->isFinished()) {
                $r = $a->result;
                $add($a->finished_at, '✅', 'Прошёл диагностику', 'action', $a->isBasic() ? '🌱 начальный, верно '.DiagnosticSummary::correctLine($a) :
                    '~'.$r['low'].'–'.$r['high'].', потенциал '.$r['potential'].'+, точки роста: '.DiagnosticSummary::weakTitles($a)
                    .($a->target_score !== null ? ', цель '.$a->target_score : ''));
            } elseif ($a->last_answer_at) {
                $add($a->last_answer_at, '⏸', 'Последний ответ в диагностике', 'action',
                    'блок '.min($a->block_index + 1, count($a->block_order)).' из '.count($a->block_order));
            }
            if ($a->improve) {
                $add($a->improve_at, $a->improve === 'yes' ? '🔥' : '🙅', '«Хочешь улучшить свои знания?» — '.($a->improve === 'yes' ? 'да' : 'пока нет'),
                    'action', $a->improve_source === 'bot' ? 'кнопкой в чате' : 'на странице');
            }
        }
    }

    private static function clock(int $seconds): string
    {
        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
