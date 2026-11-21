<?php

namespace App\Http\Controllers\Avito;

use App\Http\Controllers\Controller;
use App\Jobs\Avito\SendAvitoMessage;
use App\Models\AvitoMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Webhook мессенджера Авито: /api/avito/webhook/{secret}. Подпись Авито не
 * присылает — подлинность только по секрету в адресе (AVITO_WEBHOOK_SECRET),
 * адрес регистрирует `php artisan avito:webhook`.
 *
 * Авито ждёт быстрый 200 и иначе повторяет — поэтому здесь только запись в
 * avito_messages (уникальный message_id отсекает повторы) и задача в очередь.
 * Свои исходящие (author_id = наш аккаунт) тоже пишем: ответ, данный в самом
 * Авито, виден в теме чата; ушедший из Telegram — нет (from_bot).
 */
class WebhookController extends Controller
{
    private const OUT_DELAY_SECONDS = 30;

    public function __invoke(Request $request, string $secret)
    {
        $expected = (string) config('services.avito.webhook_secret');
        if ($expected === '' || ! hash_equals($expected, $secret)) {
            abort(403);
        }

        $payload = $request->json('payload');
        $value = $payload['value'] ?? null;
        if (($payload['type'] ?? null) !== 'message' || ! is_array($value) || empty($value['id']) || empty($value['chat_id'])) {
            return response()->json(['ok' => true]);
        }

        $userId = (int) ($value['user_id'] ?? 0);
        $authorId = (int) ($value['author_id'] ?? 0);
        $out = $authorId !== 0 && $authorId === $userId;

        $message = AvitoMessage::firstOrCreate(
            ['message_id' => (string) $value['id']],
            [
                'chat_id' => (string) $value['chat_id'],
                'user_id' => $userId,
                'author_id' => $authorId,
                'item_id' => isset($value['item_id']) ? (int) $value['item_id'] : null,
                'type' => mb_substr((string) ($value['type'] ?? 'text'), 0, 20),
                'direction' => $out ? AvitoMessage::OUT : AvitoMessage::IN,
                'text' => self::text($value),
                'sent_at_avito' => isset($value['created']) ? Carbon::createFromTimestamp((int) $value['created']) : null,
            ],
        );

        if ($message->wasRecentlyCreated) {
            $job = SendAvitoMessage::dispatch($message->id)->onQueue(config('telegram_funnel.queue'));
            // Наш ответ: эхо webhook-а может обогнать ReplyToAvito, который
            // помечает ушедшее из Telegram (from_bot), — даём ему время.
            if ($out) {
                $job->delay(now()->addSeconds(self::OUT_DELAY_SECONDS));
            }
        }

        return response()->json(['ok' => true]);
    }

    /** Текст для уведомления; у вложений — подпись вида «[фото]». */
    private static function text(array $value): ?string
    {
        $content = $value['content'] ?? [];

        return match ($value['type'] ?? 'text') {
            'text', 'system' => $content['text'] ?? null,
            'link' => trim(($content['link']['text'] ?? '').' '.($content['link']['url'] ?? '')) ?: '[ссылка]',
            'image' => '[фото]',
            'voice' => '[голосовое]',
            'video' => '[видео]',
            'file' => '[файл]',
            'item' => '[объявление] '.($content['item']['title'] ?? ''),
            'location' => '[геолокация] '.($content['location']['text'] ?? ''),
            'call' => '[звонок]',
            'deleted' => '[сообщение удалено]',
            default => $content['text'] ?? '['.($value['type'] ?? 'сообщение').']',
        };
    }
}
