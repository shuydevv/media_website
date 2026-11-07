<?php

namespace App\Service\Trainers;

use App\Models\TaskReview;
use App\Models\TaskRound;
use App\Models\User;
use App\Service\Bank\TaskTrainer;
use App\Service\Terms\TermAccess;

/**
 * Станция «Задания» на дашборде и в зале обществоведов.
 *
 * Норма дня — один раунд первой части или один подход второй. Ситуации:
 *  - начатый раунд — UNFINISHED;
 *  - есть ошибки на повтор — REVIEW;
 *  - сегодня ещё не занимались — NEW;
 *  - иначе DONE (в зале — заметка, если куратор проверил ответ).
 * Слово — «задание»: именительный и винительный совпадают.
 */
class TasksTrainerCard implements Trainer
{
    public function __construct(private TermAccess $access, private TaskTrainer $trainer)
    {
    }

    public function title(): string
    {
        return 'Задания';
    }

    public function url(): string
    {
        return route('student.ege.index');
    }

    public function availableFor(User $user): bool
    {
        return $this->access->allows($user);
    }

    public function words(): array
    {
        return [
            'what' => ['задание', 'задания', 'заданий'],
            'new_what' => ['новое задание', 'новых задания', 'новых заданий'],
            'items' => 'задания',
            'new_items' => 'новые задания',
        ];
    }

    public function statusFor(User $user): array
    {
        $o = $this->trainer->overview($user);
        $unfinished = $o['unfinished'];
        $checked = TaskReview::where('user_id', $user->id)->where('status', TaskReview::CHECKED)->whereNull('seen_at')->count();
        $note = $checked > 0 ? config('task_trainer.fish.checked') : null;

        if ($unfinished) {
            $left = max(1, (int) $unfinished->total - (int) $unfinished->position);

            return ['pending' => $left, 'situation' => self::UNFINISHED, 'count' => $left, 'next' => null, 'note' => $note];
        }

        if ($o['due'] > 0) {
            return ['pending' => $o['due'], 'situation' => self::REVIEW, 'count' => $o['due'], 'next' => null, 'note' => $note];
        }

        $today = now()->toDateString();
        $didToday = TaskRound::where('user_id', $user->id)->where('status', TaskRound::FINISHED)->whereDate('day', $today)->exists()
            || TaskReview::where('user_id', $user->id)->where('status', '!=', TaskReview::DRAFT)->whereDate('updated_at', $today)->exists();
        $total = array_sum(array_column($o['numbers'], 'total'));

        if (! $didToday && $total > 0) {
            $count = (int) config('task_trainer.round_size');

            return ['pending' => $count, 'situation' => self::NEW, 'count' => $count, 'next' => null, 'note' => $note];
        }

        return ['pending' => 0, 'situation' => self::DONE, 'count' => 0, 'next' => null, 'note' => $note];
    }
}
