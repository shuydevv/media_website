<?php

namespace App\Console\Commands;

use App\Models\SiteLead;
use App\Models\TelegramContact;
use App\Service\Telegram\AdminTopics;
use App\Service\Telegram\TelegramApi;
use App\Service\Telegram\TelegramApiException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Вечерняя сводка воронки в админ-группу (в общий чат группы, не в тему) —
 * одно уведомление в день вместо уведомления на каждый старт: по ней видно,
 * что реклама идёт и бот работает. Ноль стартов за день — тоже сигнал
 * (сломалось или реклама встала), поэтому сводка уходит и тогда.
 */
class TelegramDailyDigest extends Command
{
    protected $signature = 'telegram:digest';

    protected $description = 'Отправить сводку воронки бота за сегодня в админ-группу';

    public function handle(): int
    {
        $chatId = AdminTopics::adminChatId();
        $api = TelegramApi::funnel();
        if (! $chatId || ! $api->isConfigured()) {
            $this->warn('Бот воронки не настроен (.env) — сводка не отправлена.');

            return self::SUCCESS;
        }

        $since = now()->startOfDay();
        $today = TelegramContact::where('started_at', '>=', $since);

        $t = (clone $today)->select([
            DB::raw('COUNT(*) as starts'),
            DB::raw('SUM(opened_lesson_at IS NOT NULL) as opened'),
            DB::raw('SUM(lesson_watched_at IS NOT NULL OR bonus_watched_at IS NOT NULL) as watched'),
            DB::raw('SUM(rating IS NOT NULL OR bonus_rating IS NOT NULL) as rated'),
            DB::raw("SUM(COALESCE(rating = 'great', 0) + COALESCE(bonus_rating = 'great', 0)) as great"),
            DB::raw("SUM(COALESCE(rating = 'good', 0) + COALESCE(bonus_rating = 'good', 0)) as good"),
            DB::raw("SUM(COALESCE(rating = 'bad', 0) + COALESCE(bonus_rating = 'bad', 0)) as bad"),
            DB::raw('SUM(first_user_message_at IS NOT NULL) as wrote'),
        ])->first();

        $pct = fn ($n) => $t->starts > 0 ? ' ('.round($n / $t->starts * 100).'%)' : '';

        $lines = [
            '📊 <b>Сводка за '.now()->format('d.m').'</b>',
            'Запустили бота: <b>'.(int) $t->starts.'</b>',
        ];

        if ($t->starts > 0) {
            $lines[] = 'Открыли урок: '.(int) $t->opened.$pct($t->opened);
            $lines[] = 'Досмотрели урок: '.(int) $t->watched.$pct($t->watched);
            $lines[] = 'Оценили: '.(int) $t->rated.$pct($t->rated).' — 🔥 '.(int) $t->great.' · 👍 '.(int) $t->good.' · 👎 '.(int) $t->bad;
            $lines[] = 'Написали в диалог: '.(int) $t->wrote.$pct($t->wrote);

            $bySource = (clone $today)->select('source', DB::raw('COUNT(*) as c'))
                ->groupBy('source')->orderByDesc('c')->limit(8)->get();
            $lines[] = '';
            $lines[] = 'По меткам: '.$bySource->map(fn ($r) => ($r->source ? e($r->source) : 'без метки').' — '.$r->c)->implode(', ');
        }

        // Ждут ответа — по всем, не только сегодняшним: вечером видно, кому
        // надо ответить, не листая темы.
        $awaiting = TelegramContact::whereNotNull('last_user_message_at')
            ->whereNull('blocked_at')
            ->where(fn ($q) => $q->whereNull('last_admin_message_at')->orWhereColumn('last_user_message_at', '>', 'last_admin_message_at'))
            ->count();
        $leads = SiteLead::where('created_at', '>=', $since)->count();

        $lines[] = '';
        $lines[] = 'Заявок с сайта: '.$leads;
        if ($awaiting > 0) {
            $lines[] = '💬 Ждут ответа: <b>'.$awaiting.'</b>';
        }

        try {
            $api->sendMessage($chatId, implode("\n", $lines));
        } catch (TelegramApiException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
