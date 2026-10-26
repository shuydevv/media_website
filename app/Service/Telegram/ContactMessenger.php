<?php

namespace App\Service\Telegram;

use App\Models\TelegramContact;
use App\Models\TelegramMessage;
use Illuminate\Support\Facades\Log;

/**
 * Отправка человеку от бота: запись в историю, копия в тему, «печатает…»
 * перед «человеческими» сообщениями, блокировка бота человеком. Правила те
 * же, что у бота воронки (FunnelBot::sendAuto / sendHuman — там такой же
 * код; бот воронки работает на проде, поэтому переведён сюда будет отдельно).
 * Сейчас пользуется DiagBot.
 */
class ContactMessenger
{
    public function __construct(
        private readonly TelegramApi $api,
        private readonly AdminTopics $topics,
    ) {
    }

    /**
     * «Человеческое» сообщение: «печатает…» столько, сколько живой человек
     * набирал бы этот текст (Telegram гасит индикатор через 5 с — продлеваем),
     * потом текст.
     */
    public function sendHuman(TelegramContact $contact, string $step, string $html, array $extra = []): ?array
    {
        $t = config('telegram_funnel.timing');
        $length = mb_strlen(strip_tags($html));
        $remaining = (int) max($t['typing_seconds_min'], min($t['typing_seconds_max'], ceil($length / $t['typing_chars_per_second'])));

        try {
            while ($remaining > 0) {
                $this->api->sendTyping($contact->telegram_user_id);
                $chunk = min(4, $remaining);
                sleep($chunk);
                $remaining -= $chunk;
            }
        } catch (TelegramApiException) {
            // без «печатает…» сообщение всё равно уйдёт (или упадёт ниже с той же ошибкой)
        }

        return $this->sendAuto($contact, $step, $html, $extra);
    }

    /**
     * $photo — файл на диске: тогда фото, а текст — подписью к нему. Подпись
     * у Telegram — до 1024 символов; текст из админки длиннее — уйдёт без фото.
     *
     * @return array|null отправленное сообщение или null, если бот заблокирован
     */
    public function sendAuto(TelegramContact $contact, string $step, string $html, array $extra = [], ?string $photo = null): ?array
    {
        if ($contact->blocked_at) {
            return null;
        }

        if ($photo !== null && mb_strlen(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)) > 1024) {
            Log::warning("Telegram {$this->api->bot()}: текст длиннее подписи к фото, отправлен без фото", ['step' => $step]);
            $photo = null;
        }
        $send = fn (string $html) => $photo !== null
            ? $this->api->sendPhoto($contact->telegram_user_id, $photo, $html, $extra)
            : $this->api->sendMessage($contact->telegram_user_id, $html, $extra);

        try {
            try {
                $sent = $send($html);
            } catch (TelegramApiException $e) {
                // Премиум-эмодзи ({e:…} в тексте из админки), которые Telegram
                // боту не разрешает, валят отправку целиком — повторяем с
                // обычными эмодзи-заменителями.
                if ($e->errorCode !== 400 || ! str_contains($html, '<tg-emoji')) {
                    throw $e;
                }
                Log::warning("Telegram {$this->api->bot()}: премиум-эмодзи отклонены, отправлено с обычными", ['step' => $step, 'error' => $e->getMessage()]);
                $html = preg_replace('~<tg-emoji emoji-id="\d+">(.*?)</tg-emoji>~su', '$1', $html);
                $sent = $send($html);
            }
        } catch (TelegramApiException $e) {
            if (! $e->isBlockedByUser()) {
                throw $e;
            }
            $contact->forceFill(['blocked_at' => now()])->save();
            $this->topics->note($contact, '⛔ Не доставлено: человек заблокировал бота.');
            $this->topics->refreshStatus($contact);

            return null;
        }

        TelegramMessage::create([
            'telegram_contact_id' => $contact->id,
            'direction' => TelegramMessage::DIRECTION_OUT,
            'kind' => TelegramMessage::KIND_AUTO,
            'step' => $step,
            'text' => html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5),
            'telegram_message_id' => $sent['message_id'] ?? null,
        ]);

        $this->topics->note($contact, '🤖 <i>Бот'.($photo !== null ? ' (с картинкой)' : '').":</i>\n".preg_replace('~<a href="[^"]*">(.*?)</a>~su', '$1', $html));

        return $sent;
    }

    public function answerCallback(string $callbackId, ?string $text = null): void
    {
        try {
            $this->api->call('answerCallbackQuery', array_filter(['callback_query_id' => $callbackId, 'text' => $text]));
        } catch (TelegramApiException) {
            // кнопка просто перестанет «крутиться» сама через пару секунд
        }
    }

    /** Убрать кнопки под сообщением; $suffix — дописать строку в конец текста. */
    public function closeButtons(int|string $chatId, int $messageId, ?array $original = null, ?string $suffix = null): void
    {
        try {
            if ($original !== null && $suffix !== null) {
                $this->api->call('editMessageText', [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'text' => ($original['text'] ?? '')."\n\n".$suffix,
                    'entities' => $original['entities'] ?? [],
                    'link_preview_options' => ['is_disabled' => true],
                    'reply_markup' => ['inline_keyboard' => []],
                ]);
            } else {
                $this->api->call('editMessageReplyMarkup', [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'reply_markup' => ['inline_keyboard' => []],
                ]);
            }
        } catch (TelegramApiException $e) {
            // Уже без кнопок или сообщение старше 48 ч — не критично.
            Log::info("Telegram {$this->api->bot()}: не удалось убрать кнопки", ['error' => $e->getMessage()]);
        }
    }
}
