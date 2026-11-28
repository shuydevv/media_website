<?php

namespace App\Service\LiveChat;

use App\Models\ChatMessage;
use App\Models\ChatStatus;
use App\Models\Lesson;
use App\Models\User;

/**
 * Статусы учеников (админ, прямо на уроке): плашка у имени во всех сообщениях,
 * старых тоже. Один статус на ученика — новый заменяет прежний. Сообщения
 * ученика на уроке получают новую версию, чтобы браузеры перерисовали плашку.
 */
class LiveChatStatuses
{
    public function __construct(private LiveChatService $chat)
    {
    }

    /** @throws ChatRefused */
    public function set(Lesson $lesson, User $by, int $userId, string $text, string $color, bool $forever): void
    {
        abort_unless($by->isAdmin(), 403);
        $text = trim($text);
        if ($text === '' || mb_strlen($text) > 24) {
            throw new ChatRefused('status', 'Статус — от 1 до 24 символов');
        }
        $student = $userId < LiveChatService::GUEST_BASE ? User::find($userId) : null;
        if (! $student || LiveChatService::isTeam($student)) {
            throw new ChatRefused('status', 'Статус — только ученикам');
        }
        $color = in_array($color, ChatStatus::COLORS, true) ? $color : 'blue';
        $this->apply($lesson, $userId, fn () => ChatStatus::updateOrCreate(['user_id' => $userId], [
            'lesson_id' => $forever ? null : $lesson->id, 'text' => $text, 'color' => $color, 'set_by' => $by->id,
        ]));
    }

    public function remove(Lesson $lesson, User $by, int $userId): void
    {
        abort_unless($by->isAdmin(), 403);
        $this->apply($lesson, $userId, fn () => ChatStatus::where('user_id', $userId)->delete());
    }

    /** @return array<int, array{t: string, c: string}> user_id → статус, действующий на этом уроке */
    public function forUsers(int $lessonId, array $userIds): array
    {
        if (! $userIds) {
            return [];
        }

        return ChatStatus::whereIn('user_id', $userIds)
            ->where(fn ($q) => $q->whereNull('lesson_id')->orWhere('lesson_id', $lessonId))->get()
            ->mapWithKeys(fn ($s) => [$s->user_id => ['t' => $s->text, 'c' => $s->color, 'f' => $s->lesson_id === null]])->all();
    }

    private function apply(Lesson $lesson, int $userId, callable $fn): void
    {
        $this->chat->mutate($this->chat->chatFor($lesson), function ($v) use ($lesson, $userId, $fn) {
            $fn();
            ChatMessage::where('lesson_id', $lesson->id)->where('user_id', $userId)->update(['version' => $v]);
        });
    }
}
