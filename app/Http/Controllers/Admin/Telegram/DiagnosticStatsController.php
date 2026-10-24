<?php

namespace App\Http\Controllers\Admin\Telegram;

use App\Http\Controllers\Controller;
use App\Service\Diagnostic\DiagnosticStats;
use App\Service\Telegram\TelegramBots;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Аналитика бота диагностики: воронка, где бросают, результаты, вопросы (DiagnosticStats). */
class DiagnosticStatsController extends Controller
{
    public function __invoke(Request $request)
    {
        $from = $this->date($request->string('from')->toString());
        $to = $this->date($request->string('to')->toString());

        $stats = new DiagnosticStats($from, $to);
        $contacts = $stats->contacts();
        $finished = DiagnosticStats::finished($contacts);
        $dropOffs = DiagnosticStats::dropOffs($contacts);

        return view('admin.telegram.diagnostic', [
            'funnel' => DiagnosticStats::funnel($contacts),
            'bySource' => DiagnosticStats::bySource($contacts),
            'dropOffs' => $dropOffs,
            'results' => DiagnosticStats::results($finished),
            'questions' => $stats->questions($contacts, array_keys($dropOffs['questions'])),
            'recent' => $finished->sortByDesc('finished_at')->take(30)->load('contact'),
            'from' => $from?->format('Y-m-d'),
            'to' => $to?->format('Y-m-d'),
            'configured' => [
                'TELEGRAM_DIAG_BOT_TOKEN' => (bool) TelegramBots::token(TelegramBots::DIAG),
                'TELEGRAM_DIAG_WEBHOOK_SECRET' => TelegramBots::webhookSecret(TelegramBots::DIAG) !== '',
            ],
        ]);
    }

    private function date(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
