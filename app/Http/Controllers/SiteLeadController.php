<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Telegram\LessonLinkController;
use App\Jobs\Telegram\SendSiteLead;
use App\Models\SiteLead;
use App\Models\TelegramContact;
use App\Service\Telegram\FunnelBot;
use App\Service\Telegram\LeadContact;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Форма «Записаться» на страницах пробных уроков
 * (partials/lead-modal): имя + логин Telegram или телефон → site_leads → тема
 * «📝 Заявки с сайта» в админ-группе бота воронки (SendSiteLead).
 *
 * Пришёл на сайт из бота (cookie персональной ссылки) — Telegram уже
 * известен: логин не спрашиваем, заявка сразу связывается с контактом,
 * открывается тема, а Александр пишет первым (FunnelBot::leadFromSite).
 *
 * Не LeadController: тот обслуживает формы /repetitor (телефон/WhatsApp) и
 * шлёт в свой старый чат.
 */
class SiteLeadController extends Controller
{
    public function store(Request $request)
    {
        // Ловушка для ботов: поле скрыто от людей, а спам-скрипты заполняют
        // всё подряд. Делаем вид, что всё прошло, — чтобы не подсказывать.
        if (filled($request->input('website'))) {
            return redirect('/thank-you');
        }

        $known = $this->knownContact($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'telegram' => [$known ? 'nullable' : 'required', 'string', 'max:100'],
            'cta' => ['nullable', 'string', 'max:64'],
            'cta_label' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'string', 'max:500'],
        ], [
            'name.required' => 'Укажите имя',
            'telegram.required' => 'Укажите логин в Telegram или телефон',
        ]);

        if ($known) {
            $lead = SiteLead::create([
                'name' => trim($data['name']),
                'telegram' => $known->username,
                'telegram_contact_id' => $known->id,
                'cta' => $data['cta'] ?? null,
                'cta_label' => isset($data['cta_label']) ? trim($data['cta_label']) : null,
                'page' => $data['page'] ?? null,
                'token' => Str::random(20),
            ]);

            // Сначала тема (FunnelBot ставит её в очередь), потом строка в
            // «Заявки с сайта» — тогда она уйдёт без звука, второй раз не дёргая.
            FunnelBot::make()->leadFromSite($known, $lead);
            SendSiteLead::dispatch($lead->id)->onQueue(config('telegram_funnel.queue'));

            return redirect('/thank-you')->with('lead_known', true);
        }

        $contact = LeadContact::parse($data['telegram']);
        if ($contact === null) {
            throw ValidationException::withMessages([
                'telegram' => 'Укажите логин в Telegram (например, @ivanov) или номер телефона',
            ]);
        }

        $lead = SiteLead::create([
            'name' => trim($data['name']),
            'telegram' => $contact['telegram'],
            'phone' => $contact['phone'],
            'cta' => $data['cta'] ?? null,
            'cta_label' => isset($data['cta_label']) ? trim($data['cta_label']) : null,
            'page' => $data['page'] ?? null,
            'token' => Str::random(20),
        ]);

        SendSiteLead::dispatch($lead->id)->onQueue(config('telegram_funnel.queue'));

        // Страница «Спасибо» покажет кнопку запуска бота именно для этой заявки.
        return redirect('/thank-you')->with('lead_bot_url', $lead->botStartUrl());
    }

    /** Контакт бота по cookie персональной ссылки (см. Telegram\LessonLinkController). */
    public static function knownContact(Request $request): ?TelegramContact
    {
        $token = (string) $request->cookie(LessonLinkController::COOKIE);

        return $token !== '' ? TelegramContact::where('link_token', $token)->first() : null;
    }
}
