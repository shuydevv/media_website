<?php

namespace App\Http\Controllers\LiveChat;

use App\Http\Controllers\Controller;
use App\Models\ChatReactionType;
use App\Service\LiveChat\LiveChatService;
use Illuminate\Http\Request;

/**
 * Песочница чата трансляций: настоящий интерфейс чата (public/js/live-chat.js)
 * + «ученики»-боты по сценарию урока (public/js/live-chat-sandbox.js). Сервер
 * чата подменён в браузере — в базе ничего не создаётся, реальные ученики и
 * уроки не затрагиваются. Только для команды; работает и при
 * LIVE_CHAT_ENABLED=false — чтобы опробовать чат до включения на уроках.
 */
class SandboxController extends Controller
{
    public function __invoke(Request $request, LiveChatService $chat)
    {
        $user = $request->user();
        abort_unless(LiveChatService::isTeam($user), 403);

        $asStudent = $request->query('as') === 'student';
        // Уровень акулы ученика в песочнице: ?level=2 — проверить, как выглядит
        // запрет эмоций и эмодзи в имени. По умолчанию 4 — можно всё.
        $level = max(1, min(8, (int) $request->query('level', 4)));
        $viewer = $asStudent
            ? ['id' => 900001, 'n' => $chat->studentName($user), 'team' => false, 'admin' => false, 'b' => null,
                'av' => $user->avatar_url, 'real' => trim($user->full_name) ?: $user->name]
            : ['id' => (int) $user->id, 'n' => $chat->teamName($user), 'team' => true, 'admin' => $user->isAdmin(),
                'b' => $chat->teamBadge($user), 'av' => $user->avatar_url, 'real' => null];

        $reactions = ChatReactionType::orderBy('sort')->orderBy('id')->get()->map->toClient()->values();
        $u = fn (string $p) => '/__sandbox/'.$p;

        $config = [
            'mode' => 'live',
            'lesson' => 0,
            'csrf' => '',
            'me' => [
                'id' => $viewer['id'], 'name' => $viewer['n'], 'badge' => $viewer['b'], 'team' => $viewer['team'],
                'admin' => $viewer['admin'], 'demo' => false, 'muted' => null, 'write' => true,
                'guest' => false, 'level' => $asStudent ? $level : 0,
                'emotion' => $asStudent && $level >= config('live_chat.emotion_level'), 'emotionAt' => null,
                'emojiName' => $asStudent ? $level >= config('live_chat.emoji_name_level') : true,
            ],
            'askName' => $asStudent,
            'state' => ['s' => 'open', 'opensAt' => null, 'opensLabel' => null, 'breakUntil' => null],
            'reactions' => $reactions,
            'lottie' => asset('js/vendor/lottie_light.min.js'),
            'poll' => [config('live_chat.poll_visible_ms'), config('live_chat.poll_hidden_ms')],
            'max' => config('live_chat.message_max'),
            'nameMax' => config('live_chat.name_max'),
            'emotions' => app(\App\Service\LiveChat\LiveChatRoom::class)->emotionCatalog(),
            'emotionMax' => config('live_chat.emotion_max'),
            'emotionLevel' => config('live_chat.emotion_level'),
            'emojiNameLevel' => config('live_chat.emoji_name_level'),
            'url' => [
                'feed' => $u('feed?k=s'), 'older' => $u('older?k=s'), 'thread' => $u('thread?k=s'), 'replay' => $u('replay?k=s'),
                'send' => $u('send'), 'name' => $u('name'), 'unpin' => $u('pin'),
                'react' => $u('messages/0/reactions'), 'answer' => $u('messages/0/answer'),
                'delete' => $u('messages/0'), 'pin' => $u('messages/0/pin'),
                'mute' => $u('mutes/0'), 'unmute' => $u('mutes/0'),
                'stats' => $u('stats?k=s'), 'emotion' => $u('emotions'), 'support' => $u('emotions/0/support'),
                'vote' => $u('poll/vote'), 'pollStart' => $u('poll'), 'pollClose' => $u('poll'),
                'break' => $u('break'), 'quiet' => $u('quiet'),
            ],
        ];

        return view('live-chat.sandbox', [
            'config' => $config,
            'sandbox' => [
                'viewer' => $viewer,
                'asStudent' => $asStudent,
                'mainReactions' => $reactions->where('main', true)->where('active', true)->pluck('id')->values(),
                'heart' => $reactions->firstWhere('emoji', '❤️')['id'] ?? $reactions->first()['id'] ?? null,
                'level' => $level,
                'cfg' => [
                    'emotionSeconds' => config('live_chat.emotion_seconds'), 'emotionCooldown' => config('live_chat.emotion_cooldown'),
                    'emotionMax' => config('live_chat.emotion_max'), 'emotionLevel' => config('live_chat.emotion_level'),
                    'emojiNameLevel' => config('live_chat.emoji_name_level'),
                    'pollSeconds' => config('live_chat.poll_seconds'), 'pollResult' => config('live_chat.poll_result_seconds'),
                ],
            ],
            'asStudent' => $asStudent,
            'video' => preg_match('/^[A-Za-z0-9_-]{4,64}$/', (string) $request->query('video')) ? $request->query('video') : null,
        ]);
    }
}
