<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Jobs\Telegram\RunFunnelStep;
use App\Models\TelegramContact;
use App\Service\Telegram\FunnelLessons;
use Illuminate\Http\Request;

/**
 * Персональная ссылка на пробный урок из бота: /go/{token}/{урок} →
 * отметка «открыл урок» на контакте → редирект на /trial/{slug}. Отсюда в
 * аналитике шаг «старт → открыл урок».
 *
 * Засчитывается только первое открытие. Ботов-превьюшников (Telegram сам
 * ходит по ссылкам из сообщений, чтобы нарисовать превью) отсекаем по
 * User-Agent — иначе каждый контакт «открывал» бы урок в момент отправки.
 */
class LessonLinkController extends Controller
{
    public function __invoke(Request $request, string $token, string $lesson)
    {
        if (! FunnelLessons::exists($lesson)) {
            abort(404);
        }
        $target = route('main.trial', FunnelLessons::slug($lesson));

        $contact = TelegramContact::where('link_token', $token)->first();
        if (! $contact || preg_match('/bot|crawler|spider|preview|facebookexternalhit/i', (string) $request->userAgent())) {
            return redirect()->away($target);
        }

        $column = $lesson === $contact->lesson ? 'opened_lesson_at' : 'opened_bonus_at';
        if ($contact->{$column} === null) {
            $contact->forceFill([$column => now()])->save();

            $label = $column === 'opened_lesson_at' ? 'урок' : 'бонусный урок';
            foreach ([
                ['note', ['html' => "👀 Открыл {$label} «".e(FunnelLessons::title($lesson)).'»']],
                ['refresh_status', []],
            ] as [$step, $payload]) {
                RunFunnelStep::dispatch($contact->id, $step, $payload)->onQueue(config('telegram_funnel.queue'));
            }
        }

        return redirect()->away($target);
    }
}
