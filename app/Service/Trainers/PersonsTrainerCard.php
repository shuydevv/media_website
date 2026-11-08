<?php

namespace App\Service\Trainers;

use App\Models\User;
use App\Service\Dates\HistAccess;
use App\Service\Persons\PersonTrainer;
use Illuminate\Support\Carbon;

/** Тренажёр личностей по истории на дашборде. Ситуации — как у DatesTrainerCard. */
class PersonsTrainerCard implements Trainer
{
    public function __construct(private HistAccess $access, private PersonTrainer $trainer)
    {
    }

    public function title(): string
    {
        return 'Личности';
    }

    public function url(): string
    {
        return route('student.persons.index');
    }

    public function availableFor(User $user): bool
    {
        return $this->access->allows($user);
    }

    public function words(): array
    {
        return [
            'what' => ['личность', 'личности', 'личностей'],
            'new_what' => ['новая личность', 'новые личности', 'новых личностей'],
            'items' => 'личности',
            'new_items' => 'новые личности',
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

        [$situation, $count] = match (true) {
            $review['status'] === 'in_progress' => [self::UNFINISHED, $review['count']],
            $learn['status'] === 'in_progress' => [self::UNFINISHED, $learn['count']],
            $review['status'] === 'available' => [
                $this->trainer->overdueCount($user) > 0 ? self::OVERDUE : self::REVIEW,
                $review['count'],
            ],
            $learn['status'] === 'available' => [
                $this->trainer->nearNextLevel($user) ? self::NEW_NEAR_LEVEL : self::NEW,
                $learn['count'],
            ],
            default => [self::DONE, 0],
        };

        $next = null;
        if ($situation === self::DONE) {
            $upcoming = $review['next'] ?? null;
            if ($upcoming && $upcoming['date']->isSameDay(Carbon::tomorrow())) {
                $next = $upcoming['count'];
            }
        }

        return ['pending' => $pending, 'situation' => $situation, 'count' => $count, 'next' => $next];
    }
}
