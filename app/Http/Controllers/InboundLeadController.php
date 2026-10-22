<?php

namespace App\Http\Controllers;

use App\Jobs\Telegram\SendInboundLead;
use App\Models\InboundLead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Заявки с других моих сайтов: POST /api/inbound-lead (JSON или обычная
 * форма) → inbound_leads → своя тема сайта в админ-группе бота воронки
 * (SendInboundLead). Сайты и их ключи — config/inbound_leads.php.
 *
 * Поля: key (или заголовок X-Lead-Key), name, method, contact; website —
 * ловушка для ботов, на сайте должно быть скрыто и пустое.
 *
 * Всегда JSON, без редиректов: отправляют из JS или PHP с чужого домена.
 */
class InboundLeadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $site = $this->site((string) ($request->header('X-Lead-Key') ?? $request->input('key')));
        if ($site === null) {
            return response()->json(['ok' => false, 'error' => 'Неизвестный ключ сайта'], 401);
        }

        // Как в SiteLeadController: делаем вид, что всё прошло, — чтобы не подсказывать.
        if (filled($request->input('website'))) {
            return response()->json(['ok' => true]);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'method' => ['nullable', 'string', 'max:50'],
            'contact' => ['required', 'string', 'max:100'],
        ], [
            'name.required' => 'Укажите имя',
            'contact.required' => 'Укажите телефон или Telegram',
        ]);
        if ($validator->fails()) {
            return response()->json(['ok' => false, 'errors' => $validator->errors()], 422);
        }
        $data = $validator->validated();

        $lead = InboundLead::create([
            'site' => $site,
            'name' => trim($data['name']),
            'method' => isset($data['method']) ? trim($data['method']) : null,
            'contact' => trim($data['contact']),
        ]);

        SendInboundLead::dispatch($lead->id)->onQueue(config('telegram_funnel.queue'));

        return response()->json(['ok' => true]);
    }

    /** slug сайта по его ключу; пустые ключи (не заданы в .env) не подходят никому. */
    private function site(string $key): ?string
    {
        if ($key === '') {
            return null;
        }

        foreach (config('inbound_leads.sites', []) as $slug => $site) {
            if (filled($site['key'] ?? null) && hash_equals((string) $site['key'], $key)) {
                return $slug;
            }
        }

        return null;
    }
}
