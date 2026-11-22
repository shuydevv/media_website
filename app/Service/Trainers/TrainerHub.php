<?php

namespace App\Service\Trainers;

use App\Models\User;

/**
 * Все тренажёры, открытые ученику, — для блока с рыбой на дашборде: общий
 * счётчик дел и куда ведёт кнопка «Тренажёры». Что скажет рыба — см.
 * FishSpeech. Список тренажёров — config/trainers.php.
 */
class TrainerHub
{
    /**
     * @return array{trainers: array, pending: int, url: ?string, plan: ?array}
     *   trainers — [{trainer, title, url, status}]; url — куда ведёт кнопка
     *   «Тренажёры»: в «Качалку», если ученику открыт хоть один
     *   тренажёр (даже с одним там видны снаряды «в разработке» и второй
     *   зал); null — кнопки нет; plan — «План на сегодня»
     *   (DailyPlanService::sync()), null — ученик не выбрал норму.
     */
    public function forUser(User $user): array
    {
        $trainers = [];
        foreach (config('trainers.list', []) as $class) {
            /** @var Trainer $trainer */
            $trainer = app($class);
            if (!$trainer->availableFor($user)) {
                continue;
            }

            $trainers[] = [
                'trainer' => $trainer,
                'title' => $trainer->title(),
                'url' => $trainer->url(),
                'status' => $trainer->statusFor($user),
            ];
        }

        $plan = $trainers ? app(DailyPlanService::class)->sync($user, $trainers) : null;
        if ($plan !== null) {
            $trainers = $this->withinPlan($trainers, $plan);
        }

        return [
            'trainers' => $trainers,
            'pending' => array_sum(array_map(fn ($t) => (int) $t['status']['pending'], $trainers)),
            'url' => $trainers ? route('student.trainers.index') : null,
            'plan' => $plan,
        ];
    }

    /**
     * Счётчики, снаряды и рыба считают только то, что осталось по «Плану
     * на сегодня»: тренажёр вне плана (или с уже сделанным пунктом) для
     * них — «на сегодня всё», хотя открыт и в нём можно заниматься.
     */
    private function withinPlan(array $trainers, array $plan): array
    {
        $items = [];
        foreach ($plan['items'] as $item) {
            $items[$item['key']] = $item;
        }

        foreach ($trainers as &$t) {
            $item = $items[get_class($t['trainer'])] ?? null;
            $status = $t['status'];

            if ($item && !$item['done']) {
                if ($status['situation'] !== Trainer::UNFINISHED) {
                    $status['count'] = min((int) $status['count'], $item['quota']);
                    $status['pending'] = $status['count'];
                }
            } elseif ($status['situation'] !== Trainer::DONE) {
                $status = [
                    'pending' => 0,
                    'situation' => Trainer::DONE,
                    'count' => 0,
                    'next' => null,
                    'kind' => null,
                    'note' => $status['note'] ?? ($item ? 'План здесь выполнен — дальше по желанию' : 'Сегодня не в плане — можно по желанию'),
                    // Не в плане — не значит «пройдено»: в зале без галочки.
                    'off_plan' => $item === null,
                ];
            }

            $t['status'] = $status;
        }
        unset($t);

        return $trainers;
    }
}
