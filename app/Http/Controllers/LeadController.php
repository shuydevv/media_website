<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LeadController extends Controller
{
    /** =======================
     *  Telegram hardcoded cfg
     *  ======================= */
    private const TG_BOT_TOKEN  = '5910283688:AAGo0furmlOMxZwLicJendluOUGyaJ7odR8'; // <-- сюда токен
    private const TG_CHAT_ID    = '-890259428';                      // <-- сюда chat_id (или -1001234567890)
    private const TG_API_BASE   = 'https://api.telegram.org';
    private const TG_METHOD     = 'sendMessage';
    private const TG_PARSE_MODE = 'HTML';
    private const TG_DISABLE_WEB_PREVIEW = true;

    public function store(Request $request)
    {
        // Валидация — подстрой под свои поля
        $data = $request->validate([
            'name'    => 'nullable|string|max:255',
            'phone'   => 'required|string|max:50',
            'email'   => 'nullable|email|max:255',
            'message' => 'nullable|string|max:2000',
        ]);

        // Проверка наличия "конфига" (константы)
        if (trim(self::TG_BOT_TOKEN) === '' || trim(self::TG_CHAT_ID) === '') {
            $debug = [
                'where'             => 'missing tg hardcoded config',
                'bot_token_present' => trim(self::TG_BOT_TOKEN) !== '',
                'chat_id'           => self::TG_CHAT_ID,
            ];
            Log::error('Telegram config (hardcoded) error', $debug);

            if (config('app.debug')) {
                dd($debug);
            }

            return back()
                ->withErrors(['form' => 'Ошибка конфигурации Telegram (пустой токен или chat_id).'])
                ->withInput();
        }

        // Безопасное экранирование для HTML parse_mode
        $safe = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Сбор текста сообщения
        $rows   = [];
        $rows[] = '<b>Новая заявка</b>';
        $rows[] = 'Страница: ' . $safe($request->fullUrl());
        $rows[] = 'Форма: ' . $safe($request->input('form') ?? $request->input('source') ?? 'не указано');

        if (isset($data['name']))    { $rows[] = 'Имя: ' . $safe($data['name']); }
        if (isset($data['phone']))   { $rows[] = 'Телефон: ' . $safe($data['phone']); }
        if (isset($data['email']))   { $rows[] = 'Email: ' . $safe($data['email']); }
        if (isset($data['message'])) { $rows[] = 'Сообщение: ' . $safe($data['message']); }

        // Подхватим любые доп. поля формы (кроме _token и уже учтённых)
        $extra = collect($request->except(['_token']))->forget(array_keys($data))->all();
        if (!empty($extra)) {
            $rows[] = '';
            $rows[] = '<b>Дополнительно:</b>';
            foreach ($extra as $k => $v) {
                if (is_array($v)) {
                    $v = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                $rows[] = $safe($k) . ': ' . $safe($v);
            }
        }

        $text = implode("\n", $rows);

        // Формируем URL метода API
        $url = rtrim(self::TG_API_BASE, '/') . '/bot' . self::TG_BOT_TOKEN . '/' . self::TG_METHOD;

        try {
            $resp = Http::asForm()
                ->timeout(15)
                ->connectTimeout(10)
                ->post($url, [
                    'chat_id'                  => self::TG_CHAT_ID,
                    'text'                     => $text,
                    'parse_mode'               => self::TG_PARSE_MODE,
                    'disable_web_page_preview' => self::TG_DISABLE_WEB_PREVIEW,
                ]);

            if ($resp->failed()) {
                $debug = [
                    'where'   => 'telegram sendMessage failed',
                    'status'  => $resp->status(),
                    'reason'  => $resp->reason(),
                    'body'    => $resp->body(),
                    'json'    => $resp->json(),
                    // замаскируем токен, если выведется
                    'url'     => preg_replace('/bot(\d+):[A-Za-z0-9_-]+/', 'bot$1:***', $url),
                    'payload' => [
                        'chat_id'    => self::TG_CHAT_ID,
                        'text'       => $text,
                        'parse_mode' => self::TG_PARSE_MODE,
                    ],
                ];

                Log::error('Telegram send failed (hardcoded)', $debug);

                if (config('app.debug')) {
                    dd($debug); // В dev-режиме покажем всю причину сразу
                }

                return back()
                    ->withErrors(['form' => 'Не удалось отправить заявку. Попробуйте ещё раз.'])
                    ->withInput();
            }

            return redirect('/thank-you')->with('sent', true);
        } catch (\Throwable $e) {
            $debug = [
                'where'   => 'exception',
                'message' => $e->getMessage(),
                'file'    => $e->getFile() . ':' . $e->getLine(),
            ];

            Log::error('Telegram exception (hardcoded)', $debug);

            if (config('app.debug')) {
                dd($debug);
            }

            return back()
                ->withErrors(['form' => 'Не удалось отправить заявку. Попробуйте ещё раз.'])
                ->withInput();
        }
    }
}
