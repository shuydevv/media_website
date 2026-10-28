<?php

namespace App\Service\Telegram\Broadcast;

use App\Models\TelegramBroadcast;
use App\Models\TelegramBroadcastRecipient;
use App\Models\TelegramContact;
use App\Service\Telegram\AdminTopics;
use App\Service\Telegram\TelegramApi;
use App\Service\Telegram\TelegramApiException;
use App\Service\Telegram\TelegramBots;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Рассылки в админ-группе — тема «📣 Рассылка» (служебная, ключ broadcast;
 * заводится командой /broadcast). Ведёт её бот воронки, как и остальные
 * служебные темы: FunnelBot отдаёт сюда сообщения группы, правки и нажатия
 * кнопок bc:*.
 *
 *   Сообщение админа в теме (текст, фото, видео, альбом) → черновик и
 *   карточка ответом на него: сколько получат, кто не получит и почему,
 *   кнопки. Ничего не уходит, пока не нажато «Отправить» и затем «Да».
 *   Правка исходного сообщения меняет и рассылку: копируется то, что в теме
 *   в момент отправки.
 *
 *   Ответ на карточку: @логин, id, ссылка на тему или упоминание — исключить
 *   из этой рассылки; «вернуть …» — вернуть; «18:30» / «завтра 10:00» /
 *   «29.10 10:00» — запланировать на это время.
 *
 *   /norassylka в теме человека — исключить из всех рассылок, /rassylka —
 *   вернуть (поле broadcast_opt_out_at; то же — в карточке в админке).
 *
 * Получателей считает BroadcastAudience, отправляет BroadcastSender.
 * Карточка — единственное, что бот правит при отправке: в группу Telegram
 * пускает ~20 сообщений в минуту, поэтому копий рассылки в темы людей нет,
 * а правка карточки — не чаще CARD_REFRESH_SECONDS.
 */
class BroadcastDesk
{
    public const TOPIC_KEY = 'broadcast';

    public const STATE_MAIN = 'main';

    public const STATE_CONFIRM = 'confirm';

    public const STATE_TIME = 'time';

    public const STATE_PLAN = 'plan';

    private const CARD_REFRESH_SECONDS = 15;

    private const LIST_PAGE = 25;

    private const BOT_ICONS = [TelegramBots::LESSON => '🎯', TelegramBots::DIAG => '🧪'];

    public function __construct(private readonly TelegramApi $api)
    {
    }

    public static function make(): self
    {
        return new self(TelegramApi::funnel());
    }

    public static function topicId(): ?int
    {
        $id = DB::table('telegram_service_topics')->where('key', self::TOPIC_KEY)->value('topic_id');

        return $id ? (int) $id : null;
    }

    public static function topicUrl(): ?string
    {
        $chatId = AdminTopics::adminChatId();
        $topicId = self::topicId();

        return $chatId && $topicId ? 'https://t.me/c/'.preg_replace('/^-100/', '', (string) $chatId).'/'.$topicId : null;
    }

    // ---------- сообщения группы ----------

    /**
     * Сообщение в админ-группе (от FunnelBot). true — оно про рассылки и
     * дальше его разбирать не нужно.
     */
    public function handleAdminMessage(array $message): bool
    {
        if ($message['from']['is_bot'] ?? false) {
            return false;
        }
        $text = $message['text'] ?? '';
        $threadId = ($message['is_topic_message'] ?? false) ? (int) ($message['message_thread_id'] ?? 0) : null;
        $ourTopic = $threadId && $threadId === self::topicId();

        if (preg_match('~^/broadcast(?:@\w+)?(?![A-Za-z0-9_])~', $text)) {
            $this->openTopic();

            return true;
        }

        if ($threadId && ! $ourTopic && preg_match('~^/(no)?rassylka(?:@\w+)?(?![A-Za-z0-9_])~', $text, $m)) {
            $this->optOutFromTopic($threadId, ($m[1] ?? '') !== '');

            return true;
        }

        if (! $ourTopic) {
            return false;
        }

        // Наша тема: служебные сообщения, заметки (//) и команды — мимо.
        if (isset($message['forum_topic_created']) || isset($message['forum_topic_edited']) || isset($message['pinned_message'])) {
            return true;
        }
        $body = ltrim($message['text'] ?? $message['caption'] ?? '');
        if (str_starts_with($body, '//') || preg_match('~^/[A-Za-z]~', $body)) {
            return true;
        }

        // В теме форума у каждого сообщения reply_to_message — начало темы;
        // настоящий ответ — на другое сообщение.
        $reply = $message['reply_to_message'] ?? null;
        if ($reply && (int) $reply['message_id'] !== $threadId) {
            if ($broadcast = TelegramBroadcast::where('card_message_id', $reply['message_id'])->first()) {
                $this->handleCardReply($broadcast, $message);

                return true;
            }
            if ($reply['from']['is_bot'] ?? false) {
                $this->say('Чтобы исключить человека или выбрать время, ответьте на <b>карточку</b> рассылки — сообщение с кнопками.', $message['message_id']);

                return true;
            }
        }

        $this->createDraft($message);

        return true;
    }

    /** Правка сообщения в теме: исходник черновика — обновить превью в карточке. */
    public function handleEdited(array $message): bool
    {
        $threadId = ($message['is_topic_message'] ?? false) ? (int) ($message['message_thread_id'] ?? 0) : null;
        if (! $threadId || $threadId !== self::topicId()) {
            return false;
        }

        $broadcast = TelegramBroadcast::whereIn('status', [TelegramBroadcast::STATUS_DRAFT, TelegramBroadcast::STATUS_SCHEDULED])
            ->where('source_chat_id', $message['chat']['id'])
            ->get()
            ->first(fn (TelegramBroadcast $b) => in_array((int) $message['message_id'], array_map('intval', $b->source_message_ids), true));
        if ($broadcast) {
            $text = $message['text'] ?? $message['caption'] ?? null;
            // В альбоме подпись — у одного из сообщений: чужая правка её не стирает.
            if ($text !== null || count($broadcast->source_message_ids) === 1) {
                $broadcast->forceFill(['text' => $text])->save();
            }
            $this->refreshCard($broadcast);
        }

        return true;
    }

    private function createDraft(array $message): void
    {
        $text = $message['text'] ?? $message['caption'] ?? null;
        $mediaType = AdminTopics::mediaType($message);

        // Альбом приходит отдельными сообщениями с общим media_group_id —
        // одна рассылка. Воркер очереди telegram один, части идут по порядку.
        $group = $message['media_group_id'] ?? null;
        if ($group && ($broadcast = TelegramBroadcast::where('media_group_id', $group)->where('status', TelegramBroadcast::STATUS_DRAFT)->first())) {
            $ids = array_map('intval', $broadcast->source_message_ids);
            $ids[] = (int) $message['message_id'];
            $ids = array_values(array_unique($ids));
            sort($ids); // copyMessages требует порядок по возрастанию
            $broadcast->forceFill([
                'source_message_ids' => $ids,
                'media_count' => count($ids),
                'media_type' => $broadcast->media_type === $mediaType ? $mediaType : 'album',
                'text' => $broadcast->text ?? $text,
            ])->save();
            $this->refreshCard($broadcast);

            return;
        }

        $from = $message['from'] ?? [];
        $broadcast = TelegramBroadcast::create([
            'source_chat_id' => $message['chat']['id'],
            'source_message_ids' => [(int) $message['message_id']],
            'media_group_id' => $group,
            'text' => $text,
            'media_type' => $mediaType,
            'media_count' => $mediaType ? 1 : 0,
            'author_telegram_id' => $from['id'] ?? null,
            'author_name' => trim(($from['first_name'] ?? '').' '.($from['last_name'] ?? '')) ?: null,
        ]);

        try {
            $card = $this->api->sendMessage(AdminTopics::adminChatId(), $this->cardHtml($broadcast), [
                'message_thread_id' => self::topicId(),
                'reply_parameters' => ['message_id' => (int) $message['message_id']],
                'reply_markup' => ['inline_keyboard' => $this->cardKeyboard($broadcast)],
            ]);
            $broadcast->forceFill(['card_message_id' => $card['message_id'], 'card_refreshed_at' => now()])->save();
        } catch (TelegramApiException $e) {
            Log::warning('Telegram broadcast: не удалось отправить карточку', ['broadcast' => $broadcast->id, 'error' => $e->getMessage()]);
        }
    }

    private function handleCardReply(TelegramBroadcast $broadcast, array $message): void
    {
        $text = trim($message['text'] ?? '');
        if (! $broadcast->isEditable()) {
            $this->say('Рассылка #'.$broadcast->id.' уже '.($broadcast->status === TelegramBroadcast::STATUS_CANCELLED ? 'удалена' : 'отправлена').' — менять её нельзя.', $message['message_id']);

            return;
        }

        $at = self::parseTime($text);
        if (is_string($at)) {
            $this->say($at, $message['message_id']);

            return;
        }
        if ($at !== null) {
            $broadcast->forceFill(['pending_at' => $at, 'card_state' => self::STATE_PLAN])->save();
            $this->refreshCard($broadcast);

            return;
        }

        $return = (bool) preg_match('~^вернуть~iu', $text);
        [$found, $missing] = $this->resolvePeople($message);
        if ($found === [] && $missing === []) {
            $this->say('Не понял. Ответьте на карточку @логином, id или ссылкой на тему человека — исключу из этой рассылки; «вернуть @логин» — верну; «завтра 10:00» — запланирую.', $message['message_id']);

            return;
        }

        if ($found !== []) {
            $this->setExcluded($broadcast, array_keys($found), ! $return);
        }
        $lines = [];
        if ($found !== []) {
            $lines[] = ($return ? '↩️ Вернул в рассылку #' : '🚫 Исключил из рассылки #').$broadcast->id.': '.implode(', ', array_map('e', $found));
        }
        if ($missing !== []) {
            $lines[] = '❓ Не нашёл среди тех, кто запускал ботов: '.implode(', ', array_map('e', $missing));
        }
        $this->say(implode("\n", $lines), $message['message_id']);
    }

    /** Исключить из этой рассылки (или вернуть) — из Telegram и из админки. */
    public function setExcluded(TelegramBroadcast $broadcast, array $telegramUserIds, bool $exclude): void
    {
        $ids = collect($broadcast->excludedUserIds());
        $ids = $exclude ? $ids->merge(array_map('intval', $telegramUserIds))->unique() : $ids->diff(array_map('intval', $telegramUserIds));
        $broadcast->forceFill(['excluded_user_ids' => $ids->values()->all()])->save();
        $this->refreshCard($broadcast);
    }

    /** Исключить человека из всех рассылок (или вернуть) — у всех его контактов. */
    public function setOptOut(array $telegramUserIds, bool $optOut): void
    {
        TelegramContact::whereIn('telegram_user_id', $telegramUserIds)
            ->update(['broadcast_opt_out_at' => $optOut ? now() : null]);

        // Карточки последних черновиков и планов — брошенные старые черновики не трогаем.
        $editable = TelegramBroadcast::whereIn('status', [TelegramBroadcast::STATUS_DRAFT, TelegramBroadcast::STATUS_SCHEDULED])->latest('id')->limit(5)->get();
        foreach ($editable as $broadcast) {
            $this->refreshCard($broadcast);
        }
    }

    private function optOutFromTopic(int $threadId, bool $optOut): void
    {
        $contact = TelegramContact::where('topic_id', $threadId)->first();
        if (! $contact) {
            $this->sayIn($threadId, 'Это не тема человека — /norassylka и /rassylka работают в темах людей.');

            return;
        }

        $this->setOptOut([(int) $contact->telegram_user_id], $optOut);
        $this->sayIn($threadId, $optOut
            ? '🔕 '.e($contact->displayName()).' исключён из всех рассылок. Вернуть — /rassylka'
            : '🔔 '.e($contact->displayName()).' снова получает рассылки. Исключить — /norassylka');
    }

    private function openTopic(): void
    {
        try {
            (new AdminTopics($this->api))->postToService(self::TOPIC_KEY, implode("\n", [
                '📣 <b>Рассылки</b>',
                '',
                'Напишите сюда сообщение — текст, фото, видео или альбом, с любым оформлением. Сразу оно не уйдёт: бот ответит карточкой — сколько человек получат, кто нет и почему, — и отправит только после «🚀 Отправить» и подтверждения.',
                '',
                '• Исключить из рассылки — ответьте на карточку @логином, id или ссылкой на тему; «вернуть @логин» — вернуть.',
                '• Исключить из всех рассылок — /norassylka в теме человека, вернуть — /rassylka.',
                '• Запланировать — кнопкой или ответом на карточку: «18:30», «завтра 10:00», «29.10 10:00».',
                '• «👁 Тест себе» — прислать вам в личку так, как увидят люди (бот должен быть у вас запущен).',
                '• Сообщение с // — заметка, не рассылка.',
            ]), true);
        } catch (TelegramApiException $e) {
            Log::warning('Telegram broadcast: не удалось завести тему', ['error' => $e->getMessage()]);
        }
    }

    // ---------- кнопки ----------

    public function handleCallback(array $callback): void
    {
        $id = $callback['id'];
        if (! preg_match('~^bc:(\d+):(\w+)(?::(\w+))?(?::(\w+))?$~', $callback['data'] ?? '', $m)
            || ! AdminTopics::isAdminChat($callback['message']['chat']['id'] ?? 0)) {
            $this->answer($id);

            return;
        }
        $broadcast = TelegramBroadcast::find($m[1]);
        if (! $broadcast) {
            $this->answer($id, 'Рассылка не найдена');

            return;
        }
        [$action, $arg, $arg2] = [$m[2], $m[3] ?? null, $m[4] ?? null];

        // Действия, которые карточку не меняют (start() перерисует её сам).
        $own = match ($action) {
            'noop' => [null],
            'list' => [$this->showList($broadcast, $arg ?? 'r', (int) $arg2, $callback['message'])],
            'close' => [$this->deleteMessage((int) $callback['message']['message_id'])],
            'test' => [$this->sendTest($broadcast, $callback['from'] ?? [])],
            'go' => [$broadcast->card_state !== self::STATE_CONFIRM || ! $broadcast->isEditable() ? 'Нажмите «Отправить» ещё раз'
                : (BroadcastSender::make()->start($broadcast) ? 'Отправка началась' : 'Уже отправляется')],
            default => null,
        };
        if ($own !== null) {
            $this->answer($id, $own[0]);

            return;
        }

        $this->answer($id, $this->applyCardAction($broadcast, $action, $arg, $callback['from'] ?? []));
        $this->refreshCard($broadcast->refresh());
    }

    /** Кнопка, меняющая рассылку; вернёт подсказку для нажавшего или null. */
    private function applyCardAction(TelegramBroadcast $broadcast, string $action, ?string $arg, array $from): ?string
    {
        if ($action === 'refresh') {
            return null;
        }
        if ($action === 'stop') {
            if ($broadcast->status !== TelegramBroadcast::STATUS_SENDING) {
                return 'Рассылка уже не отправляется';
            }
            BroadcastSender::make()->stop($broadcast, 'остановил '.trim(($from['first_name'] ?? '').' '.($from['last_name'] ?? '')));

            return 'Остановлено';
        }
        if (! $broadcast->isEditable()) {
            return 'Рассылка уже '.mb_strtolower(TelegramBroadcast::STATUS_LABELS[$broadcast->status]);
        }

        $set = fn (array $fields) => $broadcast->forceFill($fields)->save();
        switch ($action) {
            case 'aud':
                if (array_key_exists((string) $arg, TelegramBroadcast::AUDIENCE_LABELS)) {
                    $set(['audience' => $arg]);
                }

                return null;
            case 'send':
                $set(['card_state' => self::STATE_CONFIRM]);

                return null;
            case 'sched':
                $set(['card_state' => self::STATE_TIME]);

                return null;
            case 'back':
                $set(['card_state' => self::STATE_MAIN, 'pending_at' => null]);

                return null;
            case 'at':
                return $this->pickTime($broadcast, (int) $arg);
            case 'plan':
                return $this->confirmPlan($broadcast);
            case 'unplan':
                $set(['card_state' => self::STATE_MAIN, 'status' => TelegramBroadcast::STATUS_DRAFT, 'scheduled_at' => null]);

                return 'Снято с плана';
            case 'cancel':
                $set(['card_state' => self::STATE_MAIN, 'status' => TelegramBroadcast::STATUS_CANCELLED]);

                return 'Черновик удалён';
        }

        return null;
    }

    private function pickTime(TelegramBroadcast $broadcast, int $timestamp): ?string
    {
        $at = CarbonImmutable::createFromTimestamp($timestamp, config('app.timezone'));
        if ($at->lte(now()->addMinute())) {
            return 'Это время уже прошло — выберите другое';
        }
        $broadcast->forceFill(['pending_at' => $at, 'card_state' => self::STATE_PLAN])->save();

        return null;
    }

    private function confirmPlan(TelegramBroadcast $broadcast): ?string
    {
        if ($broadcast->card_state !== self::STATE_PLAN || ! $broadcast->pending_at || $broadcast->pending_at->lte(now())) {
            $broadcast->forceFill(['card_state' => self::STATE_TIME, 'pending_at' => null])->save();

            return 'Время прошло — выберите заново';
        }
        $broadcast->forceFill([
            'status' => TelegramBroadcast::STATUS_SCHEDULED,
            'scheduled_at' => $broadcast->pending_at,
            'pending_at' => null,
            'card_state' => self::STATE_MAIN,
        ])->save();

        return 'Запланировано на '.self::when($broadcast->scheduled_at);
    }

    private function sendTest(TelegramBroadcast $broadcast, array $from): string
    {
        try {
            $this->api->call('copyMessages', [
                'chat_id' => $from['id'] ?? 0,
                'from_chat_id' => $broadcast->source_chat_id,
                'message_ids' => $broadcast->source_message_ids,
            ]);

            return 'Отправил вам в личку от бота пробного урока';
        } catch (TelegramApiException $e) {
            if ($e->isBlockedByUser() || str_contains($e->getMessage(), 'chat not found')) {
                return 'Бот не может написать вам первым: откройте бота пробного урока в личке и нажмите «Старт»';
            }

            return 'Не вышло: '.mb_strimwidth($e->getMessage(), 0, 150, '…');
        }
    }

    // ---------- карточка ----------

    /** Перерисовать карточку; $force = false — не чаще CARD_REFRESH_SECONDS (во время отправки). */
    public function refreshCard(TelegramBroadcast $broadcast, bool $force = true): void
    {
        if (! $broadcast->card_message_id) {
            return;
        }
        if (! $force && $broadcast->card_refreshed_at && $broadcast->card_refreshed_at->gt(now()->subSeconds(self::CARD_REFRESH_SECONDS))) {
            return;
        }

        try {
            $this->api->call('editMessageText', [
                'chat_id' => AdminTopics::adminChatId(),
                'message_id' => $broadcast->card_message_id,
                'text' => $this->cardHtml($broadcast),
                'parse_mode' => 'HTML',
                'link_preview_options' => ['is_disabled' => true],
                'reply_markup' => ['inline_keyboard' => $this->cardKeyboard($broadcast)],
            ]);
        } catch (TelegramApiException $e) {
            if (! str_contains($e->getMessage(), 'not modified')) {
                Log::warning('Telegram broadcast: не удалось обновить карточку', ['broadcast' => $broadcast->id, 'error' => $e->getMessage()]);
            }
        }
        $broadcast->forceFill(['card_refreshed_at' => now()])->save();
    }

    /** Итог отдельным сообщением — правка карточки уведомления не даёт. */
    public function announceFinish(TelegramBroadcast $broadcast): void
    {
        $s = $this->stats($broadcast);
        $html = ($broadcast->status === TelegramBroadcast::STATUS_DONE ? '✅ <b>Рассылка #'.$broadcast->id.' отправлена</b>' : '⏹ <b>Рассылка #'.$broadcast->id.' остановлена</b>'
            .($broadcast->stop_reason ? ': '.e($broadcast->stop_reason) : ''))
            ."\n".$this->statsLine($s);

        try {
            $this->api->sendMessage(AdminTopics::adminChatId(), $html, array_filter([
                'message_thread_id' => self::topicId(),
                'reply_parameters' => $broadcast->card_message_id ? ['message_id' => (int) $broadcast->card_message_id, 'allow_sending_without_reply' => true] : null,
            ]));
        } catch (TelegramApiException $e) {
            Log::warning('Telegram broadcast: не удалось написать итог', ['broadcast' => $broadcast->id, 'error' => $e->getMessage()]);
        }
    }

    public function cardHtml(TelegramBroadcast $broadcast): string
    {
        $head = '📣 <b>Рассылка #'.$broadcast->id.'</b> · '.mb_strtolower(TelegramBroadcast::STATUS_LABELS[$broadcast->status]);
        $lines = [$head, 'Сообщение: '.e($broadcast->preview()), 'Кому: '.TelegramBroadcast::AUDIENCE_LABELS[$broadcast->audience]];

        if ($broadcast->isEditable()) {
            $a = BroadcastAudience::for($broadcast);
            $byBot = $a->byBot();
            $split = $broadcast->audience === TelegramBroadcast::AUDIENCE_ALL
                ? ' ('.self::BOT_ICONS[TelegramBots::LESSON].' урок '.($byBot[TelegramBots::LESSON] ?? 0).' · '.self::BOT_ICONS[TelegramBots::DIAG].' диагностика '.($byBot[TelegramBots::DIAG] ?? 0).')'
                : '';
            $lines[] = '';
            $lines[] = '👥 Получат: <b>'.$a->recipients->count().'</b>'.$split;
            if ($a->excluded->isNotEmpty()) {
                $reasons = collect($a->excludedByReason())->map(fn ($n, $r) => BroadcastAudience::REASON_LABELS[$r].' '.$n)->implode(' · ');
                $lines[] = '🚫 Не получат: '.$a->excluded->count().' — '.$reasons;
            }
            if ($a->duplicates > 0 && $broadcast->audience === TelegramBroadcast::AUDIENCE_ALL) {
                $lines[] = '👯 В обоих ботах: '.$a->duplicates.' — получат один раз, от бота, которым пользовались последним';
            }
            if ($broadcast->status === TelegramBroadcast::STATUS_SCHEDULED) {
                $lines[] = '';
                $lines[] = '⏰ <b>Уйдёт '.self::when($broadcast->scheduled_at).'</b> — получатели пересчитаются в этот момент';
            }

            $lines[] = '';
            $lines[] = match ($broadcast->card_state) {
                self::STATE_CONFIRM => '<b>Отправить сейчас '.$a->recipients->count().' '.self::people($a->recipients->count()).'?</b> Отозвать отправленное нельзя.'
                    .(self::isNight() ? "\n🌙 Сейчас ".now()->format('H:i').' — люди могут спать. Может, запланировать на утро?' : ''),
                self::STATE_TIME => 'Когда отправить? Кнопкой ниже или ответом на карточку: «18:30», «завтра 10:00», «29.10 10:00».',
                self::STATE_PLAN => $broadcast->pending_at ? '<b>Запланировать на '.self::when($broadcast->pending_at).'?</b>' : 'Выберите время.',
                default => '<i>Исключить — ответьте на карточку: @логин, id или ссылка на тему; «вернуть @логин» — вернуть. Сообщение копируется из темы в момент отправки: правка исходника меняет рассылку.</i>',
            };
        } elseif ($broadcast->status !== TelegramBroadcast::STATUS_CANCELLED) {
            $s = $this->stats($broadcast);
            $lines[] = '';
            if ($broadcast->status === TelegramBroadcast::STATUS_SENDING) {
                $lines[] = '⏳ Отправлено <b>'.($s['total'] - $s['pending']).' из '.$s['total'].'</b>';
            } else {
                $lines[] = ($broadcast->status === TelegramBroadcast::STATUS_DONE ? '✅ ' : '⏹ ').$broadcast->started_at->format('d.m H:i')
                    .($broadcast->finished_at ? '–'.$broadcast->finished_at->format('H:i') : '')
                    .($broadcast->stop_reason ? ' · '.e($broadcast->stop_reason) : '');
            }
            $lines[] = $this->statsLine($s);
        }

        $lines[] = '';
        $lines[] = '<a href="'.e(route('admin.telegram.broadcasts.show', $broadcast)).'">Открыть в админке</a>'
            .($broadcast->author_name ? ' · автор '.e($broadcast->author_name) : '');

        return implode("\n", $lines);
    }

    private function cardKeyboard(TelegramBroadcast $broadcast): array
    {
        $b = fn (string $text, string $action) => ['text' => $text, 'callback_data' => "bc:{$broadcast->id}:{$action}"];

        if (! $broadcast->isEditable()) {
            return match ($broadcast->status) {
                TelegramBroadcast::STATUS_SENDING => [[$b('⏹ Остановить', 'stop'), $b('👥 Получатели', 'list:r:0')]],
                TelegramBroadcast::STATUS_CANCELLED => [],
                default => [
                    [$b('👥 Получатели', 'list:r:0'), $b('🚫 Не доставлено', 'list:x:0'), $b('💬 Ответили', 'list:a:0')],
                    [$b('🔄 Обновить', 'refresh')],
                ],
            };
        }

        return match ($broadcast->card_state) {
            self::STATE_CONFIRM => [[$b('✅ Да, отправить', 'go'), $b('← Назад', 'back')]],
            self::STATE_TIME => array_merge(
                array_chunk(array_map(fn ($at) => $b(self::when($at), 'at:'.$at->getTimestamp()), self::timePresets()), 2),
                [[$b('← Назад', 'back')]],
            ),
            self::STATE_PLAN => [[$b('✅ Запланировать', 'plan'), $b('← Назад', 'back')]],
            default => array_values(array_filter([
                array_map(fn ($aud, $label) => $b(($broadcast->audience === $aud ? '✓ ' : '').$label, 'aud:'.$aud),
                    array_keys(TelegramBroadcast::AUDIENCE_LABELS), ['🎯+🧪 Оба', '🎯 Урок', '🧪 Диагностика']),
                [$b('👥 Получат', 'list:r:0'), $b('🚫 Не получат', 'list:x:0')],
                [$b('👁 Тест себе', 'test'), $b('🔄 Пересчитать', 'refresh')],
                $broadcast->status === TelegramBroadcast::STATUS_SCHEDULED
                    ? [$b('🚀 Отправить сейчас', 'send'), $b('✖ Снять с плана', 'unplan')]
                    : [$b('🚀 Отправить', 'send'), $b('⏰ Запланировать', 'sched')],
                $broadcast->status === TelegramBroadcast::STATUS_DRAFT ? [$b('🗑 Удалить черновик', 'cancel')] : null,
            ])),
        };
    }

    /** @return array{total: int, pending: int, sent: int, blocked: int, failed: int, replied: int} */
    public function stats(TelegramBroadcast $broadcast): array
    {
        $by = $broadcast->recipients()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return [
            'total' => (int) $by->sum(),
            'pending' => (int) (($by[TelegramBroadcastRecipient::STATUS_PENDING] ?? 0) + ($by[TelegramBroadcastRecipient::STATUS_SENDING] ?? 0)),
            'sent' => (int) ($by[TelegramBroadcastRecipient::STATUS_SENT] ?? 0),
            'blocked' => (int) ($by[TelegramBroadcastRecipient::STATUS_BLOCKED] ?? 0),
            'failed' => (int) ($by[TelegramBroadcastRecipient::STATUS_FAILED] ?? 0),
            'replied' => $broadcast->recipients()->whereNotNull('replied_at')->count(),
        ];
    }

    private function statsLine(array $s): string
    {
        return '✉️ Доставлено '.$s['sent'].' из '.$s['total']
            .($s['blocked'] ? ' · ⛔ заблокировали бота '.$s['blocked'] : '')
            .($s['failed'] ? ' · ⚠️ ошибок '.$s['failed'] : '')
            .($s['pending'] ? ' · ⏳ не отправлено '.$s['pending'] : '')
            .' · 💬 ответили '.$s['replied'];
    }

    // ---------- списки ----------

    /**
     * Список страницами: r — получатели, x — не получат / не доставлено,
     * a — ответили. С карточки — новым сообщением, листание — правкой его же.
     */
    private function showList(TelegramBroadcast $broadcast, string $kind, int $page, array $callbackMessage): ?string
    {
        $rows = $this->listRows($broadcast, $kind);
        $pages = max(1, (int) ceil(count($rows) / self::LIST_PAGE));
        $page = max(0, min($page, $pages - 1));

        $title = match ($kind) {
            'x' => $broadcast->isEditable() ? '🚫 Не получат' : '🚫 Не доставлено',
            'a' => '💬 Ответили',
            default => $broadcast->isEditable() ? '👥 Получат' : '👥 Получатели',
        };
        $lines = [$title.' — рассылка #'.$broadcast->id.' · '.count($rows).($pages > 1 ? ' · стр. '.($page + 1).'/'.$pages : '')];
        foreach (array_slice($rows, $page * self::LIST_PAGE, self::LIST_PAGE) as $i => $row) {
            $lines[] = ($page * self::LIST_PAGE + $i + 1).'. '.$row;
        }
        if ($rows === []) {
            $lines[] = '— никого';
        }

        $cb = fn (string $text, string $action) => ['text' => $text, 'callback_data' => "bc:{$broadcast->id}:{$action}"];
        $nav = $pages > 1 ? [
            $cb('‹', 'list:'.$kind.':'.max(0, $page - 1)),
            $cb(($page + 1).'/'.$pages, 'noop'),
            $cb('›', 'list:'.$kind.':'.min($pages - 1, $page + 1)),
        ] : [];
        $markup = ['inline_keyboard' => array_values(array_filter([$nav, [$cb('✖ Закрыть', 'close')]]))];
        $html = implode("\n", $lines);

        try {
            if ((int) $callbackMessage['message_id'] === (int) $broadcast->card_message_id) {
                $this->api->sendMessage(AdminTopics::adminChatId(), $html, [
                    'message_thread_id' => self::topicId(),
                    'reply_parameters' => ['message_id' => (int) $broadcast->card_message_id],
                    'reply_markup' => $markup,
                    'disable_notification' => true,
                ]);
            } else {
                $this->api->call('editMessageText', [
                    'chat_id' => AdminTopics::adminChatId(),
                    'message_id' => $callbackMessage['message_id'],
                    'text' => $html,
                    'parse_mode' => 'HTML',
                    'link_preview_options' => ['is_disabled' => true],
                    'reply_markup' => $markup,
                ]);
            }
        } catch (TelegramApiException $e) {
            if (! str_contains($e->getMessage(), 'not modified')) {
                return 'Не вышло показать список: '.mb_strimwidth($e->getMessage(), 0, 120, '…');
            }
        }

        return null;
    }

    /** @return string[] строки списка, HTML */
    private function listRows(TelegramBroadcast $broadcast, string $kind): array
    {
        if ($broadcast->isEditable()) {
            $a = BroadcastAudience::for($broadcast);

            return match ($kind) {
                'x' => $a->excluded->map(fn ($e) => $this->personLine($e['contact']).' — '.BroadcastAudience::REASON_LABELS[$e['reason']])->all(),
                'a' => [],
                default => $a->recipients->map(fn (TelegramContact $c) => $this->personLine($c))->all(),
            };
        }

        $statusLabels = [
            TelegramBroadcastRecipient::STATUS_SENT => '✅',
            TelegramBroadcastRecipient::STATUS_BLOCKED => '⛔ заблокировал бота',
            TelegramBroadcastRecipient::STATUS_FAILED => '⚠️ ошибка',
            TelegramBroadcastRecipient::STATUS_PENDING => '⏳ не отправлено',
            TelegramBroadcastRecipient::STATUS_SENDING => '⏳ отправляется',
        ];
        $query = $broadcast->recipients()->with('contact')->orderBy('id');
        match ($kind) {
            'x' => $query->where('status', '!=', TelegramBroadcastRecipient::STATUS_SENT),
            'a' => $query->whereNotNull('replied_at')->reorder('replied_at'),
            default => null,
        };

        return $query->get()->map(fn (TelegramBroadcastRecipient $r) => $this->personLine($r->contact).' — '
            .($kind === 'a' ? 'ответил '.$r->replied_at->format('d.m H:i') : $statusLabels[$r->status]))->all();
    }

    private function personLine(TelegramContact $c): string
    {
        $name = e(mb_strimwidth($c->displayName(), 0, 40, '…'));
        $url = AdminTopics::topicUrl($c);
        $line = (self::BOT_ICONS[$c->bot] ?? '').' '.($url ? '<a href="'.e($url).'">'.$name.'</a>' : $name);
        if ($c->username && ! str_starts_with($c->displayName(), '@')) {
            $line .= ' @'.e($c->username);
        } elseif (! $c->username) {
            $line .= ' <code>'.$c->telegram_user_id.'</code>';
        }

        return $line;
    }

    // ---------- разбор ответа на карточку ----------

    /**
     * Люди из ответа на карточку: упоминания без логина (text_mention),
     *
     * @логины, ссылки на темы (t.me/c/<группа>/<тема>), числовые id.
     *
     * @return array{0: array<int, string>, 1: string[]} [telegram_user_id → имя, ненайденное]
     */
    private function resolvePeople(array $message): array
    {
        $text = $message['text'] ?? '';
        $found = [];
        $missing = [];
        $add = function (?TelegramContact $c, string $token) use (&$found, &$missing) {
            if ($c) {
                $found[(int) $c->telegram_user_id] = $c->displayName();
            } else {
                $missing[] = $token;
            }
        };

        foreach ($message['entities'] ?? [] as $entity) {
            if (($entity['type'] ?? '') === 'text_mention' && isset($entity['user']['id'])) {
                $add(TelegramContact::where('telegram_user_id', $entity['user']['id'])->first(), (string) ($entity['user']['first_name'] ?? $entity['user']['id']));
            }
        }

        if (preg_match_all('~t\.me/c/\d+/(\d+)~', $text, $m)) {
            foreach ($m[1] as $i => $topicId) {
                $add(TelegramContact::where('topic_id', $topicId)->first(), $m[0][$i]);
            }
        }
        $rest = preg_replace('~\S*t\.me/\S*~', ' ', $text);

        if (preg_match_all('~@([A-Za-z0-9_]{4,32})~', $rest, $m)) {
            foreach ($m[1] as $username) {
                $add(TelegramContact::whereRaw('lower(username) = ?', [mb_strtolower($username)])->first(), '@'.$username);
            }
        }
        if (preg_match_all('~(?<![\w@])(\d{5,15})(?!\w)~', $rest, $m)) {
            foreach ($m[1] as $userId) {
                $add(TelegramContact::where('telegram_user_id', $userId)->first(), $userId);
            }
        }

        return [$found, array_values(array_unique($missing))];
    }

    /**
     * Время из ответа на карточку: «18:30», «в 18:30», «завтра 10:00»,
     * «29.10 10:00», «29.10.2026 10:00». Без даты — ближайшее такое время.
     *
     * @return CarbonImmutable|string|null время; строка — время понятно, но не годится; null — это не время
     */
    public static function parseTime(string $text): CarbonImmutable|string|null
    {
        if (! preg_match('~^(?:(сегодня|завтра)\s+)?(?:в\s+)?(?:(\d{1,2})\.(\d{1,2})(?:\.(\d{2,4}))?\s+(?:в\s+)?)?(\d{1,2})[:.](\d{2})$~iu', trim($text), $m)) {
            return null;
        }
        [, $word, $day, $month, $year, $hour, $minute] = array_pad($m, 7, '');
        if ((int) $hour > 23 || (int) $minute > 59) {
            return 'Не понял время — пример: «завтра 10:00» или «29.10 18:30».';
        }

        $now = CarbonImmutable::now();
        if ($day !== '') {
            $year = $year === '' ? $now->year : ((int) $year < 100 ? 2000 + (int) $year : (int) $year);
            if (! checkdate((int) $month, (int) $day, $year)) {
                return 'Нет такой даты: '.$day.'.'.$month;
            }
            $at = $now->setDate($year, (int) $month, (int) $day)->setTime((int) $hour, (int) $minute);
        } else {
            $at = $now->setTime((int) $hour, (int) $minute);
            if (mb_strtolower($word) === 'завтра') {
                $at = $at->addDay();
            } elseif ($word === '' && $at->lte($now)) {
                $at = $at->addDay();
            }
        }

        if ($at->lte($now->addMinute())) {
            return 'Это время уже прошло — укажите будущее.';
        }
        if ($at->gt($now->addDays(30))) {
            return 'Запланировать можно не дальше чем на 30 дней.';
        }

        return $at;
    }

    /** @return CarbonImmutable[] */
    private static function timePresets(): array
    {
        $now = CarbonImmutable::now();
        $presets = [$now->addHour()->ceilMinutes(5)];
        if ($now->lt($now->setTime(18, 30))) {
            $presets[] = $now->setTime(19, 0);
        }
        foreach ([10, 12, 18] as $hour) {
            $presets[] = $now->addDay()->setTime($hour, 0);
        }

        return $presets;
    }

    public static function when(CarbonInterface $at): string
    {
        $at = CarbonImmutable::instance($at);
        $day = match (true) {
            $at->isToday() => 'сегодня',
            $at->isTomorrow() => 'завтра',
            default => $at->format('d.m'),
        };

        return $day.' '.$at->format('H:i');
    }

    private static function isNight(): bool
    {
        return now()->hour < 9 || now()->hour >= 22;
    }

    private static function people(int $n): string
    {
        $mod10 = $n % 10;
        $mod100 = $n % 100;

        return match (true) {
            $mod10 === 1 && $mod100 !== 11 => 'человеку',
            default => 'людям',
        };
    }

    // ---------- мелочи ----------

    private function say(string $html, int $replyTo): void
    {
        try {
            $this->api->sendMessage(AdminTopics::adminChatId(), $html, [
                'message_thread_id' => self::topicId(),
                'reply_parameters' => ['message_id' => $replyTo, 'allow_sending_without_reply' => true],
            ]);
        } catch (TelegramApiException $e) {
            Log::warning('Telegram broadcast: не удалось ответить в теме', ['error' => $e->getMessage()]);
        }
    }

    private function sayIn(int $threadId, string $html): void
    {
        try {
            $this->api->sendMessage(AdminTopics::adminChatId(), $html, ['message_thread_id' => $threadId, 'disable_notification' => true]);
        } catch (TelegramApiException $e) {
            Log::warning('Telegram broadcast: не удалось ответить в теме', ['error' => $e->getMessage()]);
        }
    }

    private function deleteMessage(int $messageId): ?string
    {
        try {
            $this->api->call('deleteMessage', ['chat_id' => AdminTopics::adminChatId(), 'message_id' => $messageId]);
        } catch (TelegramApiException) {
            return 'Не удалось убрать — удалите вручную';
        }

        return null;
    }

    private function answer(string $callbackId, ?string $text = null): void
    {
        try {
            $this->api->call('answerCallbackQuery', array_filter([
                'callback_query_id' => $callbackId,
                'text' => $text,
                'show_alert' => $text !== null && mb_strlen($text) > 60,
            ]));
        } catch (TelegramApiException) {
            // кнопка перестанет «крутиться» сама
        }
    }
}
