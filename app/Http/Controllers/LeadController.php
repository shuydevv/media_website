<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class LeadController extends Controller
{
    public function store(Request $request)
    {
        // Валидация (минимально строгая)
        $data = $request->validate([
            'name'       => ['nullable','string','max:255'],
            'method'     => ['nullable','in:whatsapp,telegram'],
            'phone'      => ['required','string','max:255'],
            'form_type'  => ['nullable','string','max:255'],
            'cta'        => ['nullable','string','max:255'],
            'cta_label'  => ['nullable','string','max:255'],
            'page'       => ['nullable','string','max:2048'],
        ]);

        // Собираем полезные поля
        $payload = [
            'site'      => 'poltavskiy-school.ru',
            'form'      => $data['form_type']  ?? '',
            'name'      => $data['name']       ?? '',
            'method'    => $data['method']     ?? '',
            'phone'     => $data['phone'],
            'cta'       => $data['cta']        ?? '',
            'cta_label' => $data['cta_label']  ?? '',
            'page'      => $data['page']       ?? $request->headers->get('referer'),
            'ip'        => $request->ip(),
            'ua'        => Str::limit($request->userAgent() ?? '', 256),
        ];

        // Экранируем под parse_mode=HTML
        $escape = fn($v) => e((string)$v);
        $lines  = [];
        foreach ($payload as $k => $v) {
            if ($v !== null && $v !== '') {
                $lines[] = '<b>'.$escape($k).':</b> '.$escape($v);
            }
        }
        $text = implode("\n", $lines);

        $token  = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');

        // Отправляем POST в Telegram
        $resp = Http::asForm()->post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id'    => $chatId,
            'parse_mode' => 'HTML',
            'text'       => $text,
        ]);

        if ($resp->ok() && data_get($resp->json(), 'ok') === true) {
            // редирект на "спасибо" — как вам удобно
            return redirect()->route('thankyou'); // создайте такой маршрут/страницу
            // или: return redirect('/thank-you');
        }

        Log::error('Telegram send failed', [
            'status' => $resp->status(),
            'body'   => $resp->body(),
        ]);

        return back()
            ->withErrors(['form' => 'Не удалось отправить заявку. Попробуйте ещё раз.'])
            ->withInput();
    }
}
