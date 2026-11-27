<?php

namespace App\Service\LiveChat;

use App\Models\KinescopeWebhookEvent;
use App\Models\Lesson;
use App\Models\LessonChat;
use App\Models\LessonLiveSegment;
use Illuminate\Support\Facades\DB;

/**
 * Сигналы «эфир пошёл» / «эфир закончился» → куски эфира урока.
 *
 * Формат уведомлений Kinescope на момент написания не проверен (документация
 * не открывалась). Поэтому: каждое уведомление пишется в журнал
 * kinescope_webhook_events; тип события берётся из первого найденного поля
 * event/type/name; урок ищется по коду трансляции (lessons.meet_link) где
 * угодно в теле уведомления — так не нужно знать, в каком поле он лежит.
 * Имена событий — config('live_chat.kinescope'). После тестового эфира
 * поправить по журналу.
 */
class LiveSignals
{
    public function handleWebhook(array $payload): KinescopeWebhookEvent
    {
        $type = $this->eventType($payload);
        $raw = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $cfg = config('live_chat.kinescope');

        $action = match (true) {
            in_array($type, $cfg['start_events'], true) => 'start',
            in_array($type, $cfg['end_events'], true) => 'end',
            default => 'ignored',
        };

        $lesson = $action === 'ignored' ? null : $this->findLesson($raw);
        if ($lesson && $action === 'start') {
            $this->start($lesson);
        } elseif ($lesson && $action === 'end') {
            $this->end($lesson);
        }

        return KinescopeWebhookEvent::create([
            'event_type' => $type,
            'lesson_id' => $lesson?->id,
            'action' => $lesson ? $action : 'ignored',
            'payload' => $raw,
        ]);
    }

    public function start(Lesson $lesson): void
    {
        $this->bumpWith($lesson, function () use ($lesson) {
            $open = LessonLiveSegment::where('lesson_id', $lesson->id)->whereNull('ended_at')->exists();
            if (! $open) {
                LessonLiveSegment::create(['lesson_id' => $lesson->id, 'started_at' => now()]);
            }

            return ['closed_at' => null]; // эфир вернулся после закрытия — тот же чат снова открыт
        });
    }

    public function end(Lesson $lesson): void
    {
        $this->bumpWith($lesson, function () use ($lesson) {
            LessonLiveSegment::where('lesson_id', $lesson->id)->whereNull('ended_at')->update(['ended_at' => now()]);

            return [];
        });
    }

    /** Изменение состояния — тоже новая версия чата: браузеры сразу видят плашку. */
    private function bumpWith(Lesson $lesson, callable $apply): void
    {
        DB::transaction(function () use ($lesson, $apply) {
            $chat = LessonChat::firstOrCreate(['lesson_id' => $lesson->id]);
            $chat = LessonChat::whereKey($chat->id)->lockForUpdate()->first();
            $chat->fill($apply());
            $chat->version++;
            $chat->save();
        });
    }

    private function eventType(array $p): ?string
    {
        foreach (['event', 'type', 'name', 'event_type'] as $k) {
            if (isset($p[$k]) && is_string($p[$k])) {
                return $p[$k];
            }
        }

        return null;
    }

    /** Урок, чей код трансляции встречается в уведомлении. Сначала — ближайшие по дате. */
    private function findLesson(string $raw): ?Lesson
    {
        $candidates = Lesson::query()
            ->whereNotNull('meet_link')->where('meet_link', '!=', '')
            ->join('course_sessions', 'course_sessions.id', '=', 'lessons.course_session_id')
            ->whereBetween('course_sessions.date', [now()->subDays(2)->toDateString(), now()->addDays(2)->toDateString()])
            ->select('lessons.*', 'course_sessions.date as session_date')
            ->get()
            ->sortBy(fn ($l) => abs(now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($l->session_date), false)));

        foreach ($candidates as $lesson) {
            $code = trim($lesson->meet_link);
            if (strlen($code) >= 6 && str_contains($raw, $code)) {
                return $lesson;
            }
        }

        return null;
    }
}
