<?php

namespace App\Http\Controllers\Admin\Telegram;

use App\Http\Controllers\Controller;
use App\Models\SiteLead;
use App\Models\TelegramContact;
use App\Service\Telegram\FunnelLessons;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Аналитика воронки бота по шагам: старт → открыл урок → оценил → написал.
 * Разбивка по меткам из deep link (какая реклама приводит смотрящих, а не
 * просто подписчиков) и по урокам. Период — по дате первого /start.
 */
class IndexController extends Controller
{
    public function __invoke(Request $request)
    {
        $from = $this->date($request->string('from')->toString());
        $to = $this->date($request->string('to')->toString());

        $base = TelegramContact::query()
            ->when($from, fn ($q) => $q->where('started_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($q) => $q->where('started_at', '<=', $to->copy()->endOfDay()));

        $columns = [
            DB::raw('COUNT(*) as starts'),
            DB::raw('SUM(opened_lesson_at IS NOT NULL) as opened'),
            DB::raw('SUM(opened_bonus_at IS NOT NULL) as opened_bonus'),
            DB::raw("SUM(rating = 'up') as liked"),
            DB::raw("SUM(rating = 'down') as disliked"),
            DB::raw('SUM(first_user_message_at IS NOT NULL) as wrote'),
            DB::raw('SUM(first_admin_message_at IS NOT NULL) as answered'),
            DB::raw('SUM(blocked_at IS NOT NULL) as blocked'),
        ];

        $total = (clone $base)->select($columns)->first();
        $bySource = (clone $base)->select(array_merge(['source'], $columns))
            ->groupBy('source')->orderByDesc('starts')->get();
        $byLesson = (clone $base)->select(array_merge(['lesson'], $columns))
            ->groupBy('lesson')->orderByDesc('starts')->get()
            ->each(fn ($row) => $row->lesson_title = FunnelLessons::exists($row->lesson) ? FunnelLessons::title($row->lesson) : $row->lesson);

        $recent = (clone $base)->latest('started_at')->limit(30)->get();

        $leads = SiteLead::query()
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to->copy()->endOfDay()))
            ->latest()->limit(50)->get();

        return view('admin.telegram.index', [
            'total' => $total,
            'bySource' => $bySource,
            'byLesson' => $byLesson,
            'recent' => $recent,
            'leads' => $leads,
            'from' => $from?->format('Y-m-d'),
            'to' => $to?->format('Y-m-d'),
            'configured' => [
                'token' => (bool) config('services.telegram_funnel.bot_token'),
                'admin_chat' => (bool) config('services.telegram_funnel.admin_chat_id'),
                'secret' => (bool) config('services.telegram_funnel.webhook_secret'),
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
