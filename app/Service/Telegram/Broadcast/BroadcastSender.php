<?php

namespace App\Service\Telegram\Broadcast;

use App\Jobs\Telegram\SendBroadcast;
use App\Models\TelegramBroadcast;
use App\Models\TelegramBroadcastRecipient;
use App\Models\TelegramContact;
use App\Models\TelegramMessage;
use App\Service\Telegram\ReminderSchedule;
use App\Service\Telegram\TelegramApi;
use App\Service\Telegram\TelegramApiException;
use App\Service\Telegram\TelegramBots;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Отправка рассылки. start() фиксирует получателей (BroadcastAudience) и
 * ставит SendBroadcast; задача шлёт порцию и ставит себя снова — так
 * отправка не держит воркер очереди telegram, остальные задачи ботов идут
 * между порциями, а «Остановить» срабатывает в пределах порции.
 *
 * Каждый получатель сначала захватывается (pending → sending одним UPDATE):
 * если задач окажется две (сторож telegram:broadcasts перезапустил
 * «зависшую»), один человек всё равно получит одно сообщение. Ошибка
 * Telegram, кроме 429, — failed без повтора: при обрыве связи сообщение
 * могло дойти, повтор дал бы дубль.
 *
 * Копия — copyMessages из темы «📣 Рассылка» ботом контакта (оба бота —
 * участники группы): текст, медиа, альбом и форматирование — как у админа.
 *
 * Рассылка — не живой диалог: first_admin_message_at не трогаем, автоматика
 * воронки продолжается. Только отложенные автосообщения ближе QUIET_MINUTES
 * сдвигаются, чтобы два сообщения не пришли подряд.
 */
class BroadcastSender
{
    /** Не больше стольких в секунду на все боты (у Telegram ~30 на бота). */
    private const PER_SECOND = 25;

    /** Порция одной задачи — по числу и по времени (timeout задачи — 120 с). */
    private const CHUNK = 100;

    private const CHUNK_SECONDS = 40;

    /** Отложенные автосообщения ближе этого после рассылки — сдвигаются. */
    public const QUIET_MINUTES = 120;

    /** Сообщение человека в течение стольких дней после рассылки — ответ на неё. */
    public const REPLY_WINDOW_DAYS = 3;

    public function __construct(private readonly BroadcastDesk $desk)
    {
    }

    public static function make(): self
    {
        return new self(BroadcastDesk::make());
    }

    /** Старт из черновика или плана; false — уже стартовала или отменена. */
    public function start(TelegramBroadcast $broadcast): bool
    {
        $started = DB::transaction(function () use ($broadcast) {
            TelegramBroadcast::whereKey($broadcast->id)->lockForUpdate()->first();
            $broadcast->refresh();
            if (! $broadcast->isEditable()) {
                return false;
            }

            $now = now();
            foreach (BroadcastAudience::for($broadcast)->recipients->chunk(500) as $chunk) {
                TelegramBroadcastRecipient::insert($chunk->map(fn (TelegramContact $c) => [
                    'telegram_broadcast_id' => $broadcast->id,
                    'telegram_contact_id' => $c->id,
                    'status' => TelegramBroadcastRecipient::STATUS_PENDING,
                ])->values()->all());
            }
            $broadcast->forceFill([
                'status' => TelegramBroadcast::STATUS_SENDING,
                'card_state' => BroadcastDesk::STATE_MAIN,
                'pending_at' => null,
                'started_at' => $now,
                'progress_at' => $now,
            ])->save();

            return true;
        });

        if ($started) {
            $this->desk->refreshCard($broadcast);
            self::dispatchChunk($broadcast);
        }

        return $started;
    }

    public static function dispatchChunk(TelegramBroadcast $broadcast, int $delaySeconds = 0): void
    {
        $job = SendBroadcast::dispatch($broadcast->id)->onQueue(config('telegram_funnel.queue'));
        if ($delaySeconds > 0) {
            $job->delay(now()->addSeconds($delaySeconds));
        }
    }

    /**
     * Порция отправки. Вернёт задержку перед следующей порцией (429 —
     * сколько сказал Telegram) или null — следующей не нужно.
     */
    public function runChunk(TelegramBroadcast $broadcast): ?int
    {
        $deadline = microtime(true) + self::CHUNK_SECONDS;
        $apis = [];

        for ($i = 0; $i < self::CHUNK && microtime(true) < $deadline; $i++) {
            // Статус перечитываем каждые 10: «Остановить» — не дольше пары секунд.
            if ($i % 10 === 0 && $broadcast->refresh()->status !== TelegramBroadcast::STATUS_SENDING) {
                return null;
            }

            $recipient = $broadcast->recipients()->where('status', TelegramBroadcastRecipient::STATUS_PENDING)->orderBy('id')->first();
            if (! $recipient) {
                $this->finishIfComplete($broadcast);

                return null;
            }
            $claimed = TelegramBroadcastRecipient::whereKey($recipient->id)
                ->where('status', TelegramBroadcastRecipient::STATUS_PENDING)
                ->update(['status' => TelegramBroadcastRecipient::STATUS_SENDING]);
            if (! $claimed) {
                continue;
            }

            $started = microtime(true);
            $contact = $recipient->contact;
            try {
                $this->sendOne($broadcast, $recipient, $contact, $apis[$contact->bot] ??= TelegramBots::api($contact->bot));
            } catch (TelegramApiException $e) {
                if ($e->isRateLimited()) {
                    $recipient->forceFill(['status' => TelegramBroadcastRecipient::STATUS_PENDING])->save();
                    $wait = ($e->retryAfter ?? 5) + 1;
                    // Пауза по просьбе Telegram — не «зависание» для сторожа.
                    $broadcast->forceFill(['progress_at' => now()->addSeconds($wait)])->save();

                    return $wait;
                }
                if (self::isSourceMissing($e)) {
                    $recipient->forceFill(['status' => TelegramBroadcastRecipient::STATUS_PENDING])->save();
                    $this->stop($broadcast, 'исходное сообщение удалено из темы — копировать нечего');

                    return null;
                }
                $recipient->forceFill([
                    'status' => $e->isBlockedByUser() ? TelegramBroadcastRecipient::STATUS_BLOCKED : TelegramBroadcastRecipient::STATUS_FAILED,
                    'error' => mb_substr($e->getMessage(), 0, 255),
                ])->save();
                if ($e->isBlockedByUser()) {
                    $contact->forceFill(['blocked_at' => now()])->save();
                }
            }

            $broadcast->forceFill(['progress_at' => now()])->save();
            $this->desk->refreshCard($broadcast, false);

            $pause = 1 / self::PER_SECOND - (microtime(true) - $started);
            if ($pause > 0) {
                usleep((int) ($pause * 1_000_000));
            }
        }

        return 0;
    }

    /** Остановка кнопкой или сбоем: кто не получил — так и остаются pending. */
    public function stop(TelegramBroadcast $broadcast, ?string $reason = null): void
    {
        $updated = TelegramBroadcast::whereKey($broadcast->id)->where('status', TelegramBroadcast::STATUS_SENDING)->update([
            'status' => TelegramBroadcast::STATUS_STOPPED,
            'finished_at' => now(),
            'stop_reason' => $reason,
        ]);
        if ($updated) {
            $broadcast->refresh();
            $this->desk->refreshCard($broadcast);
            $this->desk->announceFinish($broadcast);
        }
    }

    /**
     * Ответ человека на рассылку: первое его сообщение в течение
     * REPLY_WINDOW_DAYS после неё. Вернёт получателя, если отметили сейчас
     * (тогда бот пишет в тему, на что это ответ).
     */
    public static function recordReply(TelegramContact $contact): ?TelegramBroadcastRecipient
    {
        if (! $contact->last_broadcast_at || $contact->last_broadcast_at->lt(now()->subDays(self::REPLY_WINDOW_DAYS))) {
            return null;
        }

        $recipient = TelegramBroadcastRecipient::where('telegram_contact_id', $contact->id)
            ->where('status', TelegramBroadcastRecipient::STATUS_SENT)
            ->where('sent_at', '>=', now()->subDays(self::REPLY_WINDOW_DAYS))
            ->latest('sent_at')
            ->first();
        if (! $recipient || $recipient->replied_at) {
            return null;
        }

        $recipient->forceFill(['replied_at' => now()])->save();

        return $recipient;
    }

    private function sendOne(TelegramBroadcast $broadcast, TelegramBroadcastRecipient $recipient, TelegramContact $contact, TelegramApi $api): void
    {
        $sent = $api->call('copyMessages', [
            'chat_id' => $contact->telegram_user_id,
            'from_chat_id' => $broadcast->source_chat_id,
            'message_ids' => $broadcast->source_message_ids,
        ]);

        $now = now();
        $recipient->forceFill([
            'status' => TelegramBroadcastRecipient::STATUS_SENT,
            'telegram_message_id' => $sent[0]['message_id'] ?? null,
            'sent_at' => $now,
        ])->save();

        $contact->last_broadcast_at = $now;
        $this->postponeAutomation($contact);
        $contact->save();

        TelegramMessage::create([
            'telegram_contact_id' => $contact->id,
            'direction' => TelegramMessage::DIRECTION_OUT,
            'kind' => TelegramMessage::KIND_BROADCAST,
            'step' => 'broadcast:'.$broadcast->id,
            'text' => $broadcast->text,
            'media_type' => $broadcast->media_type,
            'telegram_message_id' => $sent[0]['message_id'] ?? null,
        ]);
    }

    /** Отложенные автосообщения ближе QUIET_MINUTES — на QUIET_MINUTES, в окно 10–21. */
    private function postponeAutomation(TelegramContact $contact): void
    {
        $after = now()->addMinutes(self::QUIET_MINUTES);
        foreach (array_keys(TelegramBots::scenario($contact->bot)?->dueSteps() ?? []) as $field) {
            if ($contact->{$field} !== null && $contact->{$field}->lt($after)) {
                $contact->{$field} = ReminderSchedule::inWindow($after);
            }
        }
    }

    private function finishIfComplete(TelegramBroadcast $broadcast): void
    {
        if ($broadcast->recipients()->whereIn('status', [TelegramBroadcastRecipient::STATUS_PENDING, TelegramBroadcastRecipient::STATUS_SENDING])->exists()) {
            return; // захвачен другой задачей — она и закончит
        }

        $updated = TelegramBroadcast::whereKey($broadcast->id)->where('status', TelegramBroadcast::STATUS_SENDING)
            ->update(['status' => TelegramBroadcast::STATUS_DONE, 'finished_at' => now()]);
        if ($updated) {
            $broadcast->refresh();
            $this->desk->refreshCard($broadcast);
            $this->desk->announceFinish($broadcast);
        }
    }

    private static function isSourceMissing(TelegramApiException $e): bool
    {
        return $e->errorCode === 400
            && (str_contains($e->getMessage(), 'message to copy not found') || str_contains($e->getMessage(), 'MESSAGE_ID_INVALID')
                || str_contains($e->getMessage(), 'messages to copy not found'));
    }

    /**
     * Сторож (telegram:broadcasts): захваченный получатель без итога дольше
     * $minutes — задача упала посреди отправки; дошло ли — неизвестно, не
     * повторяем. Рассылка без движения столько же — ставим порцию заново.
     */
    public static function recoverStuck(int $minutes = 3): void
    {
        $before = now()->subMinutes($minutes);
        foreach (TelegramBroadcast::where('status', TelegramBroadcast::STATUS_SENDING)->where('progress_at', '<', $before)->get() as $broadcast) {
            $broadcast->recipients()->where('status', TelegramBroadcastRecipient::STATUS_SENDING)->update([
                'status' => TelegramBroadcastRecipient::STATUS_FAILED,
                'error' => 'отправка прервалась — дошло ли, неизвестно',
            ]);
            $broadcast->forceFill(['progress_at' => now()])->save();
            Log::warning('Telegram broadcast: отправка без движения, порция поставлена заново', ['broadcast' => $broadcast->id]);
            self::dispatchChunk($broadcast);
        }
    }
}
