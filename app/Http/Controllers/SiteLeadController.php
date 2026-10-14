<?php

namespace App\Http\Controllers;

use App\Jobs\Telegram\SendSiteLead;
use App\Models\SiteLead;
use App\Service\Telegram\LeadContact;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Форма «Записаться» на страницах пробных уроков
 * (partials/lead-modal): имя + логин Telegram или телефон → site_leads → тема
 * «📝 Заявки с сайта» в админ-группе бота воронки (SendSiteLead).
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

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'telegram' => ['required', 'string', 'max:100'],
            'cta' => ['nullable', 'string', 'max:64'],
            'cta_label' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'string', 'max:500'],
        ], [
            'name.required' => 'Укажите имя',
            'telegram.required' => 'Укажите логин в Telegram или телефон',
        ]);

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
}
