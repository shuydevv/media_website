<?php

namespace App\Console\Commands;

use App\Models\Lesson;
use App\Service\LiveChat\LiveSignals;
use Illuminate\Console\Command;

/**
 * Ручной сигнал эфира для чата трансляции — для проверки без Kinescope
 * (локально) и на случай, если уведомление Kinescope не пришло.
 */
class LiveChatSignal extends Command
{
    protected $signature = 'live-chat:signal {lesson : id урока} {action : start|end}';

    protected $description = 'Сигнал «эфир пошёл»/«эфир закончился» для чата урока';

    public function handle(LiveSignals $signals): int
    {
        $lesson = Lesson::find($this->argument('lesson'));
        if (! $lesson) {
            $this->error('Урок не найден');

            return self::FAILURE;
        }

        match ($this->argument('action')) {
            'start' => $signals->start($lesson),
            'end' => $signals->end($lesson),
            default => $this->error('action: start или end'),
        };
        $this->info('Готово: '.$lesson->title);

        return self::SUCCESS;
    }
}
