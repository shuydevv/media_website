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
     * @return array{trainers: array, pending: int, url: ?string}
     *   trainers — [{trainer, title, url, status}]; url — куда ведёт кнопка
     *   «Тренажёры»: в «Качалку», если ученику открыт хоть один
     *   тренажёр (даже с одним там видны снаряды «в разработке» и второй
     *   зал); null — кнопки нет.
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

        return [
            'trainers' => $trainers,
            'pending' => array_sum(array_map(fn ($t) => (int) $t['status']['pending'], $trainers)),
            'url' => $trainers ? route('student.trainers.index') : null,
        ];
    }
}
