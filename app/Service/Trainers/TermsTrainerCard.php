<?php

namespace App\Service\Trainers;

use App\Models\User;
use App\Service\Terms\TermAccess;
use App\Service\Terms\TermTrainer;
use Illuminate\Support\Carbon;

/** Тренажёр терминов (задание 18) на дашборде. */
class TermsTrainerCard implements Trainer
{
    use PlanSessionRows;

    public function __construct(private TermAccess $access, private TermTrainer $trainer)
    {
    }

    public function title(): string
    {
        return 'Термины';
    }

    public function url(): string
    {
        return route('student.terms.index');
    }

    public function availableFor(User $user): bool
    {
        return $this->access->allows($user);
    }

    public function words(): array
    {
        return [
            'what' => ['термин', 'термина', 'терминов'],
            'new_what' => ['новый термин', 'новых термина', 'новых терминов'],
            'items' => 'термины',
            'new_items' => 'новые термины',
        ];
    }

    public function statusFor(User $user): array
    {
        $overview = $this->trainer->overview($user);
        $review = $overview['review'];
        $learn = $overview['learn'];

        $reviewOpen = in_array($review['status'], ['available', 'in_progress'], true);
        $learnOpen = in_array($learn['status'], ['available', 'in_progress'], true);
        $pending = ($reviewOpen ? $review['count'] : 0) + ($learnOpen ? $learn['count'] : 0);

        [$situation, $count, $kind] = match (true) {
            $review['status'] === 'in_progress' => [self::UNFINISHED, $review['count'], 'review'],
            $learn['status'] === 'in_progress' => [self::UNFINISHED, $learn['count'], 'learn'],
            $review['status'] === 'available' => [
                $this->trainer->overdueCount($user) > 0 ? self::OVERDUE : self::REVIEW,
                $review['count'],
                'review',
            ],
            $learn['status'] === 'available' => [
                $this->trainer->nearNextLevel($user) ? self::NEW_NEAR_LEVEL : self::NEW,
                $learn['count'],
                'learn',
            ],
            default => [self::DONE, 0, null],
        };

        // «Завтра повторим 6 терминов» — только если ближайшее повторение
        // действительно завтра.
        $next = null;
        if ($situation === self::DONE) {
            $upcoming = $review['next'] ?? null;
            if ($upcoming && $upcoming['date']->isSameDay(Carbon::tomorrow())) {
                $next = $upcoming['count'];
            }
        }

        return ['pending' => $pending, 'situation' => $situation, 'count' => $count, 'next' => $next, 'kind' => $kind];
    }

    public function planSessions(User $user, int $limit): array
    {
        return $this->spacedPlanSessions(\App\Models\TermSession::class, $user, $limit);
    }
}
