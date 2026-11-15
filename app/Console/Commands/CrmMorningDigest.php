<?php

namespace App\Console\Commands;

use App\Models\CrmReminder;
use App\Models\User;
use App\Service\Crm\CrmBoard;
use App\Service\Telegram\AdminTopics;
use App\Service\Telegram\TelegramApi;
use App\Service\Telegram\TelegramApiException;
use App\Support\CrmCourseState;
use App\Support\CrmDate;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Утренняя сводка CRM в админ-группу Telegram (общий чат, как вечерняя
 * telegram:digest): кому сегодня напомнить, у кого истекает, кто просрочил,
 * кто перестал заниматься, сколько денег ожидается до конца месяца. Одно
 * сообщение вместо того, чтобы открывать CRM. Письма о напоминаниях
 * (crm:notify-reminders-due) остаются как были.
 */
class CrmMorningDigest extends Command
{
    protected $signature = 'crm:morning-digest {--dry : Вывести текст в консоль, не отправлять}';

    protected $description = 'Утренняя сводка CRM в админ-группу Telegram';

    /** Сколько имён показывать в одном разделе — дальше «и ещё N». */
    private const LIST_LIMIT = 10;

    public function handle(CrmBoard $board): int
    {
        $students = $board->workingSet();

        $lines = ['☀️ <b>CRM на '.now()->format('d.m').'</b>'];

        // Наступившие напоминания (сегодня и просроченные).
        $reminders = $students->flatMap(fn (User $u) => $u->crmReminders
            ->filter(fn (CrmReminder $r) => $r->isActive())
            ->map(fn (CrmReminder $r) => '• '.$this->name($u).' — '.e($r->note)
                .($r->due_at->isToday() ? '' : ' <i>(с '.$r->due_at->format('d.m').')</i>')));
        $this->section($lines, '🔔 Напомнить сегодня', $reminders);

        // Просрочена оплата и истекает в ближайшие дни — по курсам.
        $pastDue = collect();
        $soon = collect();
        foreach ($students as $u) {
            foreach ($u->courses->whereIn('pivot.status', ['active', 'suspended']) as $course) {
                $s = CrmCourseState::for($u, $course);
                if (! $s['until']) {
                    continue;
                }
                $line = '• '.$this->name($u).' — '.e($course->title).', до '.CrmDate::format($s['until'])
                    .($s['remindedAt'] ? ' <i>(напомнил '.$s['remindedAt']->format('d.m').')</i>' : '');
                if ($s['isOverdue']) {
                    $pastDue->push($line);
                } elseif ($s['isSoon']) {
                    $soon->push($line);
                }
            }
        }
        $this->section($lines, '⛔ Просрочена оплата', $pastDue);
        $this->section($lines, '⏳ Истекает в ближайшие '.User::CRM_SOON_THRESHOLD_DAYS.' дня', $soon);

        $inactive = $students->filter(fn (User $u) => $u->crmIsInactive())
            ->map(fn (User $u) => '• '.$this->name($u).' — '.($u->crmLastActiveDate
                ? 'не заходил с '.$u->crmLastActiveDate->format('d.m')
                : 'ни разу не заходил'));
        $this->section($lines, '😴 Не занимаются '.User::CRM_INACTIVE_DAYS.'+ дней', $inactive);

        $forecast = $board->forecast($students);
        $lines[] = '';
        $lines[] = '💰 Ожидается до конца месяца: <b>'.number_format($forecast['rub'], 0, ',', ' ').' ₽</b>'
            .' ('.$forecast['students'].' учен.)';

        if (count($lines) === 3) {
            $lines[] = 'Срочного ничего — спокойного дня.';
        }

        $lines[] = '';
        $lines[] = '<a href="'.route('admin.crm.index').'">Открыть CRM</a>';

        $text = implode("\n", $lines);

        if ($this->option('dry')) {
            foreach ($lines as $line) {
                $this->line(html_entity_decode(strip_tags($line)));
            }

            return self::SUCCESS;
        }

        $chatId = AdminTopics::adminChatId();
        $api = TelegramApi::funnel();
        if (! $chatId || ! $api->isConfigured()) {
            $this->warn('Бот админ-группы не настроен (.env) — сводка не отправлена.');

            return self::SUCCESS;
        }

        try {
            $api->sendMessage($chatId, $text);
        } catch (TelegramApiException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** Раздел добавляется, только если в нём кто-то есть. */
    private function section(array &$lines, string $title, Collection $items): void
    {
        if ($items->isEmpty()) {
            return;
        }

        $lines[] = '';
        $lines[] = $title.': <b>'.$items->count().'</b>';
        foreach ($items->take(self::LIST_LIMIT) as $item) {
            $lines[] = $item;
        }
        if ($items->count() > self::LIST_LIMIT) {
            $lines[] = '…и ещё '.($items->count() - self::LIST_LIMIT);
        }
    }

    /** Имя ученика ссылкой на его карточку в CRM (поиск по email/имени). */
    private function name(User $u): string
    {
        $name = trim(($u->first_name ?? '').' '.($u->last_name ?? '')) ?: ($u->email ?: 'Ученик #'.$u->id);
        $q = $u->email ?: $name;

        return '<a href="'.e(route('admin.crm.index', ['q' => $q])).'">'.e(Str::limit($name, 40)).'</a>';
    }
}
