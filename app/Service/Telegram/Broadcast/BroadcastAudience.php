<?php

namespace App\Service\Telegram\Broadcast;

use App\Models\TelegramBroadcast;
use App\Models\TelegramContact;
use App\Service\Telegram\TelegramBots;
use Illuminate\Support\Collection;

/**
 * Кому уйдёт рассылка — по людям, а не по контактам: человек, запускавший
 * оба бота, — два контакта, но получит одно сообщение. Отправит его бот,
 * которым он пользовался последним (написал, перезапустил, запустил); если
 * там он бота заблокировал — другой.
 *
 * Не получат: исключённые из всех рассылок (broadcast_opt_out_at у любого
 * контакта человека — исключают человека, не бота), исключённые из этой
 * рассылки (excluded_user_ids) и те, кто заблокировал все свои боты из
 * аудитории. Причина — первая по этому порядку.
 *
 * Для черновика считается заново при каждом показе; при старте отправки —
 * последний раз, дальше получатели живут в telegram_broadcast_recipients.
 */
final class BroadcastAudience
{
    public const REASON_OPT_OUT = 'opt_out';

    public const REASON_HERE = 'here';

    public const REASON_BLOCKED = 'blocked';

    public const REASON_LABELS = [
        self::REASON_OPT_OUT => 'исключён из всех рассылок',
        self::REASON_HERE => 'исключён из этой рассылки',
        self::REASON_BLOCKED => 'заблокировал бота',
    ];

    /**
     * @param  Collection<int, TelegramContact>  $recipients  по одному контакту на человека
     * @param  Collection<int, array{contact: TelegramContact, reason: string}>  $excluded
     * @param  int  $duplicates  людей, у которых в аудитории два контакта
     */
    private function __construct(
        public readonly Collection $recipients,
        public readonly Collection $excluded,
        public readonly int $duplicates,
    ) {
    }

    public static function for(TelegramBroadcast $broadcast): self
    {
        $bots = $broadcast->audience === TelegramBroadcast::AUDIENCE_ALL ? TelegramBots::keys() : [$broadcast->audience];
        $optedOut = TelegramContact::whereNotNull('broadcast_opt_out_at')->pluck('telegram_user_id')
            ->map(fn ($id) => (int) $id)->flip();
        $here = array_flip($broadcast->excludedUserIds());

        $recipients = collect();
        $excluded = collect();
        $duplicates = 0;

        $people = TelegramContact::whereIn('bot', $bots)->latest('started_at')->get()->groupBy('telegram_user_id');
        foreach ($people as $userId => $contacts) {
            if ($contacts->count() > 1) {
                $duplicates++;
            }
            $latest = $contacts->sortByDesc(fn (TelegramContact $c) => self::lastActivity($c))->values();

            $reason = match (true) {
                isset($optedOut[(int) $userId]) => self::REASON_OPT_OUT,
                isset($here[(int) $userId]) => self::REASON_HERE,
                default => null,
            };
            $contact = $reason === null ? $latest->first(fn (TelegramContact $c) => $c->blocked_at === null) : null;

            if ($contact) {
                $recipients->push($contact);
            } else {
                $excluded->push(['contact' => $latest->first(), 'reason' => $reason ?? self::REASON_BLOCKED]);
            }
        }

        return new self($recipients, $excluded, $duplicates);
    }

    /** @return array<string, int> бот → число получателей через него */
    public function byBot(): array
    {
        return $this->recipients->countBy('bot')->all();
    }

    /** @return array<string, int> причина → число не получающих */
    public function excludedByReason(): array
    {
        return $this->excluded->countBy('reason')->all();
    }

    private static function lastActivity(TelegramContact $c): int
    {
        return max(
            $c->last_user_message_at?->getTimestamp() ?? 0,
            $c->last_start_at?->getTimestamp() ?? 0,
            $c->started_at?->getTimestamp() ?? 0,
        );
    }
}
