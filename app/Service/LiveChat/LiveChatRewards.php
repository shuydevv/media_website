<?php

namespace App\Service\LiveChat;

use App\Models\ChatMessage;
use App\Models\ChatReward;
use App\Models\Lesson;
use App\Models\User;
use App\Service\FishFoodService;

/**
 * Корм за верный ответ в чате трансляции. Даёт команда — админ или куратор,
 * всегда config('live_chat.reward_corm') (сейчас +1), одно сообщение — одна
 * награда: двое одному ответу корм не дают. Под сообщением — плашка корма
 * для всех (fd в ленте), у ученика — уведомление (его рисует браузер, увидев
 * fd у своего сообщения). Гостям, команде и самому себе — нельзя.
 */
class LiveChatRewards
{
    public function __construct(private LiveChatService $chat, private FishFoodService $fish)
    {
    }

    /** @throws ChatRefused */
    public function give(Lesson $lesson, ChatMessage $msg, User $by): void
    {
        $student = $this->assertCanReward($lesson, $msg, $by);
        $corm = (int) config('live_chat.reward_corm', 1);

        $given = $this->chat->mutate($this->chat->chatFor($lesson), function ($v) use ($lesson, $msg, $by, $student, $corm) {
            if (ChatReward::where('message_id', $msg->id)->lockForUpdate()->exists()) {
                return false; // кто-то из команды успел раньше
            }
            ChatReward::create([
                'lesson_id' => $lesson->id,
                'message_id' => $msg->id,
                'user_id' => $student->id,
                'given_by' => $by->id,
                'giver_name' => $this->chat->teamName($by),
                'corm' => $corm,
            ]);
            $msg->forceFill(['version' => $v])->save();

            return true;
        });

        if (! $given) {
            throw new ChatRefused('rewarded', 'За этот ответ корм уже дали');
        }
        $this->fish->awardChatAnswer($student, $corm);
    }

    /** Забрать (промахнулись сообщением). Любой из команды. */
    public function take(Lesson $lesson, ChatMessage $msg, User $by): void
    {
        abort_unless(LiveChatService::isTeam($by), 403);
        $reward = $this->chat->mutate($this->chat->chatFor($lesson), function ($v) use ($msg) {
            $r = ChatReward::where('message_id', $msg->id)->lockForUpdate()->first();
            if (! $r) {
                return null;
            }
            $r->delete();
            $msg->forceFill(['version' => $v])->save();

            return $r;
        });
        if ($reward && ($student = User::find($reward->user_id))) {
            $this->fish->revokeChatAnswer($student, (int) $reward->corm);
        }
    }

    /** @throws ChatRefused */
    private function assertCanReward(Lesson $lesson, ChatMessage $msg, User $by): User
    {
        abort_unless(LiveChatService::isTeam($by), 403);
        abort_unless((int) $msg->lesson_id === (int) $lesson->id, 404);
        if ($msg->deleted_at) {
            throw new ChatRefused('deleted', 'Сообщение удалено');
        }
        if ($msg->user_id >= LiveChatService::GUEST_BASE) {
            throw new ChatRefused('guest', 'Гость по ссылке — у него нет акулы и корма');
        }
        $student = User::find($msg->user_id);
        if (! $student || LiveChatService::isTeam($student)) {
            throw new ChatRefused('team', 'Корм — только ученикам');
        }

        return $student;
    }
}
