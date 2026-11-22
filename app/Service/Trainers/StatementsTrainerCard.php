<?php

namespace App\Service\Trainers;

use App\Models\User;
use App\Service\Statements\StatementTrainer;
use App\Service\Terms\TermAccess;

/**
 * «Верные суждения» на дашборде и в зале обществоведов.
 *
 * Норма дня — одна сессия. Ситуации:
 *  - начатая сессия — UNFINISHED;
 *  - есть ошибки на повтор — REVIEW (OVERDUE, если часть ждёт со вчера);
 *  - сегодня ещё не занимались и есть новые суждения — NEW;
 *  - иначе DONE.
 * Слово — «суждение»: у него именительный и винительный совпадают, общие
 * фразы рыбы («Осталось :what», «Повторим :what») не ломаются.
 */
class StatementsTrainerCard implements Trainer
{
    use PlanSessionRows;

    public function __construct(private TermAccess $access, private StatementTrainer $trainer)
    {
    }

    public function title(): string
    {
        return 'Верные суждения';
    }

    public function url(): string
    {
        return route('student.statements.index');
    }

    public function availableFor(User $user): bool
    {
        return $this->access->allows($user);
    }

    public function words(): array
    {
        return [
            'what' => ['суждение', 'суждения', 'суждений'],
            'new_what' => ['новое суждение', 'новых суждения', 'новых суждений'],
            'items' => 'суждения',
            'new_items' => 'новые суждения',
            // Глагол пункта «Плана на сегодня»: «Разобрать 12 …».
            'do' => 'Разобрать',
        ];
    }

    public function statusFor(User $user): array
    {
        $o = $this->trainer->overview($user);
        $unfinished = $o['unfinished'];

        if ($unfinished) {
            $left = max(1, (int) $unfinished->total - (int) $unfinished->position);

            return ['pending' => $left, 'situation' => self::UNFINISHED, 'count' => $left, 'next' => null, 'kind' => 'session'];
        }

        if ($o['due'] > 0) {
            return [
                'pending' => $o['due'],
                'situation' => $o['overdue'] > 0 ? self::OVERDUE : self::REVIEW,
                'count' => $o['due'],
                'next' => null,
                'kind' => 'session',
            ];
        }

        if ($o['finished_today'] === 0 && $o['unseen'] > 0) {
            $count = min($this->trainer->sessionSize($user), $o['unseen']);

            return ['pending' => $count, 'situation' => self::NEW, 'count' => $count, 'next' => null, 'kind' => 'session'];
        }

        // Норма сделана, но корм ещё можно заработать — скажем об этом в
        // зале (note), а не ситуацией: рыба не должна звать трижды в день.
        $left = $o['corm']['left'];

        return [
            'pending' => 0,
            'situation' => self::DONE,
            'count' => 0,
            'next' => $o['due_tomorrow'] > 0 ? $o['due_tomorrow'] : null,
            'note' => $left > 0 && array_sum(array_column($o['blocks'], 'total')) > 0
                ? strtr(config('statements.phrases.hall_done_left'), [':rounds' => StatementTrainer::rounds($left)])
                : null,
        ];
    }

    public function planSessions(User $user, int $limit): array
    {
        return $this->regularPlanSessions(\App\Models\StatementSession::class, $user, $limit);
    }
}
