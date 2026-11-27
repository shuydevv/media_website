<?php

namespace App\Http\Controllers\LiveChat;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Service\LiveChat\LiveChatService;
use Illuminate\Http\Request;

/**
 * Лента чата трансляции — опрос раз в 2 секунды. В api-группе, без сессии
 * (см. LiveChatService::feedToken()); кто спрашивает — по ключу ?k=.
 * Доступ к курсу проверен при выдаче ключа (страница урока), ключ живёт 12 ч.
 */
class FeedController extends Controller
{
    public function __construct(private LiveChatService $chat)
    {
    }

    public function feed(Request $request, Lesson $lesson)
    {
        $user = $this->chat->userFromToken($request->query('k'), $lesson);
        abort_unless($user, 401);

        return response()->json($this->chat->feed($lesson, $user, (int) $request->query('v', 0)));
    }

    public function older(Request $request, Lesson $lesson)
    {
        $user = $this->chat->userFromToken($request->query('k'), $lesson);
        abort_unless($user, 401);

        return response()->json($this->chat->older($lesson, $user, (int) $request->query('before', PHP_INT_MAX)));
    }

    public function replay(Request $request, Lesson $lesson)
    {
        $user = $this->chat->userFromToken($request->query('k'), $lesson);
        abort_unless($user, 401);

        return response()->json($this->chat->replay($lesson, $user));
    }
}
