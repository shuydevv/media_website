<?php

namespace App\Service\Trainers;

/**
 * Сборка «Плана на сегодня» — чистая логика без БД (правила —
 * docs/trainers/План дня — механика.md). Что ждёт в тренажёрах, темп
 * ученика и когда тренажёр последний раз был в плане, собирает
 * DailyPlanService.
 */
class DailyPlanner
{
    // Порядок важности — как в «Качалке» (TrainerGym::URGENCY).
    public const URGENCY = [
        Trainer::UNFINISHED => 5,
        Trainer::OVERDUE => 4,
        Trainer::REVIEW => 3,
        Trainer::NEW_NEAR_LEVEL => 2,
        Trainer::NEW => 1,
        Trainer::DONE => 0,
    ];

    /**
     * @param  array<int, array{key: string, hall: string, situation: string, kind: string,
     *   count: int, sec: float, min: int, last: ?string}>  $candidates
     *   count — сколько карточек можно взять (уже с потолком сессии); sec —
     *   темп, секунд на карточку; min — меньше не даём; last — день, когда
     *   тренажёр последний раз был в плане (Y-m-d), null — не был.
     * @param  int  $budget  секунды на день
     * @param  int  $slots  сколько тренажёров в плане
     * @param  float  $backlogFactor  новое — только если повторения не больше стольких бюджетов
     * @return array<int, array{key: string, kind: string, situation: string, quota: int, sec: int}>
     *                                                                                               sec — сколько секунд займёт пункт по темпу ученика.
     */
    public function plan(array $candidates, int $budget, int $slots, float $backlogFactor): array
    {
        $candidates = array_values(array_filter(
            $candidates,
            fn ($c) => $c['situation'] !== Trainer::DONE && $c['count'] > 0
        ));

        // Завал повторения — новое подождёт: иначе долг только растёт.
        $reviewSec = 0;
        foreach ($candidates as $c) {
            if (! $this->isNew($c['situation'])) {
                $reviewSec += $c['count'] * $c['sec'];
            }
        }
        if ($reviewSec > $backlogFactor * $budget) {
            $candidates = array_values(array_filter($candidates, fn ($c) => ! $this->isNew($c['situation'])));
        }

        $picked = $this->pick($candidates, max(0, $slots));

        return $this->allot($picked, $budget);
    }

    /**
     * Самые срочные; при равной срочности — кто дольше не был в плане
     * (тренажёры чередуются по дням). Сначала по одному из каждого зала,
     * чтобы при двух курсах в план попадали оба.
     */
    private function pick(array $candidates, int $slots): array
    {
        $order = array_keys($candidates);
        usort($order, function (int $a, int $b) use ($candidates) {
            $ca = $candidates[$a];
            $cb = $candidates[$b];

            return [self::URGENCY[$cb['situation']], $ca['last'] ?? '', $a]
                <=> [self::URGENCY[$ca['situation']], $cb['last'] ?? '', $b];
        });

        $chosen = [];
        $halls = [];
        foreach ($order as $i) {
            if (count($chosen) < $slots && ! isset($halls[$candidates[$i]['hall']])) {
                $chosen[$i] = true;
                $halls[$candidates[$i]['hall']] = true;
            }
        }
        foreach ($order as $i) {
            if (count($chosen) < $slots) {
                $chosen[$i] ??= true;
            }
        }

        $out = [];
        foreach ($order as $i) {
            if (isset($chosen[$i])) {
                $out[] = $candidates[$i];
            }
        }

        return $out;
    }

    /**
     * Делит бюджет поровну; кому хватает меньше доли — получает всё, что
     * есть, а остаток уходит остальным. Недоделанная сессия входит целиком.
     */
    private function allot(array $picked, int $budget): array
    {
        $quota = [];
        $rest = $budget;
        $open = [];

        foreach ($picked as $i => $c) {
            if ($c['situation'] === Trainer::UNFINISHED) {
                $quota[$i] = $c['count'];
                $rest -= $c['count'] * $c['sec'];
            } else {
                $open[$i] = $c;
            }
        }

        $share = 0;
        while ($open) {
            $share = max(0, $rest) / count($open);
            $fits = array_filter($open, fn ($c) => $share >= $c['count'] * $c['sec']);
            if (! $fits) {
                break;
            }
            foreach ($fits as $i => $c) {
                $quota[$i] = $c['count'];
                $rest -= $c['count'] * $c['sec'];
                unset($open[$i]);
            }
        }

        foreach ($open as $i => $c) {
            $q = (int) floor($share / $c['sec']);
            $quota[$i] = min($c['count'], max($c['min'], $q));
        }

        $items = [];
        foreach ($picked as $i => $c) {
            $items[] = [
                'key' => $c['key'],
                'kind' => $c['kind'],
                'situation' => $c['situation'],
                'quota' => $quota[$i],
                'sec' => (int) round($quota[$i] * $c['sec']),
            ];
        }

        return $items;
    }

    private function isNew(string $situation): bool
    {
        return $situation === Trainer::NEW || $situation === Trainer::NEW_NEAR_LEVEL;
    }
}
