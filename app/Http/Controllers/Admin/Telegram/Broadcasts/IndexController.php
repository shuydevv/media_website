<?php

namespace App\Http\Controllers\Admin\Telegram\Broadcasts;

use App\Http\Controllers\Controller;
use App\Models\TelegramBroadcast;
use App\Models\TelegramBroadcastRecipient;
use App\Service\Telegram\Broadcast\BroadcastDesk;

/**
 * Все рассылки (создаются в теме «📣 Рассылка» админ-группы —
 * Telegram\Broadcast\BroadcastDesk): статус, сколько доставлено и ответили.
 */
class IndexController extends Controller
{
    public function __invoke()
    {
        $broadcasts = TelegramBroadcast::where('status', '!=', TelegramBroadcast::STATUS_CANCELLED)
            ->withCount([
                'recipients',
                'recipients as sent_count' => fn ($q) => $q->where('status', TelegramBroadcastRecipient::STATUS_SENT),
                'recipients as replied_count' => fn ($q) => $q->whereNotNull('replied_at'),
            ])
            ->latest('id')
            ->paginate(30);

        return view('admin.telegram.broadcasts.index', [
            'broadcasts' => $broadcasts,
            'topicUrl' => BroadcastDesk::topicUrl(),
        ]);
    }
}
