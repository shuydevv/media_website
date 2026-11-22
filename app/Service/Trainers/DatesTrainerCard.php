<?php

namespace App\Service\Trainers;

use App\Models\User;
use App\Service\Dates\DateTrainer;
use App\Service\Dates\HistAccess;
use Illuminate\Support\Carbon;

/** Тренажёр дат по истории на дашборде. Ситуации — как у TermsTrainerCard. */
class DatesTrainerCard implements Trainer
{
    use PlanSessionRows;

    public function __construct(private HistAccess $access, private DateTrainer $trainer)
    {
    }

    public function title(): string
    {
        return 'Даты';
    }

    public function url(): string
    {
        return route('student.dates.index');
    }

    public function availableFor(User $user): bool
    {
        return $this->access->allows($user);
    }

    public function words(): array
    {
        return [
            'what' => ['дата', 'даты', 'дат'],
            'new_what' => ['новая дата', 'новые даты', 'новых дат'],
            'items' => 'даты',
            'new_items' => 'новые даты',
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
        return $this->spacedPlanSessions(\App\Models\HistSession::class, $user, $limit);
    }
}
