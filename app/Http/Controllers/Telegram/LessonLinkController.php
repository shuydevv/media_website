<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Models\TelegramContact;
use App\Service\Telegram\FunnelBot;
use App\Service\Telegram\FunnelLessons;
use Illuminate\Http\Request;

/**
 * Персональная ссылка на пробный урок из бота: /go/{token}/{урок}[?t=сек] →
 * отметка «открыл урок» → редирект на /trial/{slug}. Отсюда в аналитике шаг
 * «старт → открыл урок».
 *
 * Заодно ставит cookie с токеном контакта: по нему страница урока знает,
 * кто смотрит, — показывает оценку под видео и шлёт отметки просмотра
 * (Telegram\TrialFeedbackController). Без cookie страница обычная.
 *
 * Ботов-превьюшников (Telegram сам ходит по ссылкам из сообщений, чтобы
 * нарисовать превью) отсекаем по User-Agent — иначе каждый контакт
 * «открывал» бы урок в момент отправки.
 */
class LessonLinkController extends Controller
{
    public const COOKIE = 'tg_contact';

    public function __invoke(Request $request, string $token, string $lesson)
    {
        if (! FunnelLessons::exists($lesson)) {
            abort(404);
        }

        $query = $request->integer('t') > 0 ? ['t' => $request->integer('t')] : [];
        $target = route('main.trial', ['slug' => FunnelLessons::slug($lesson)] + $query);

        $contact = TelegramContact::forBot(FunnelBot::BOT)->where('link_token', $token)->first();
        if (! $contact || preg_match('/bot|crawler|spider|preview|facebookexternalhit/i', (string) $request->userAgent())) {
            return redirect()->away($target);
        }

        FunnelBot::make()->trackOpen($contact, $lesson !== $contact->lesson);

        return redirect()->away($target)->withCookie(cookie(self::COOKIE, $contact->link_token, 60 * 24 * 90));
    }
}
