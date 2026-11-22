<?php

// «План на сегодня» в «Качалке» — посильная норма тренажёров на день.
// Механика целиком — docs/trainers/План дня — механика.md; сборка —
// App\Service\Trainers\DailyPlanner (чистая логика), снимок дня и учёт —
// App\Service\Trainers\DailyPlanService.

return [
    // Норма в минутах — выбирает сам ученик (users.trainer_daily_minutes).
    'norms' => [10, 20, 30],

    // Сколько тренажёров в плане по числу активных курсов ученика (больше
    // двух курсов пока не бывает — берётся последнее значение). Без курсов
    // (тренажёры открыты вручную) — как с одним.
    'slots' => [1 => 2, 2 => 3],

    // Добор: у каждого тренажёра свой потолок на день, и при большой норме
    // базовые тренажёры укладываются минут в 8–10. Если после них от бюджета
    // осталось from_minutes и больше — в план идёт следующий, не больше max.
    'extra' => [
        'from_minutes' => 5,
        'max' => 2,
    ],

    // Облегчённый день: урок сегодня или дедлайн несданной домашки в
    // ближайшие homework_hours часов — норма × factor, но не меньше min_minutes.
    'light' => [
        'factor' => 0.5,
        'min_minutes' => 5,
        'homework_hours' => 24,
    ],

    // Новое не попадает в план, пока долг по повторению больше стольких
    // дневных норм (в минутах по темпу ученика).
    'backlog_factor' => 2,

    // Темп ученика — медиана секунд на карточку по последним sample_size
    // законченным сессиям вида. Сессии, где на карточку вышло больше
    // default × outlier_factor (оставил вкладку открытой), не считаются;
    // итог зажат в [default / clamp, default × clamp].
    'pace' => [
        'sample_size' => 10,
        'outlier_factor' => 6,
        'clamp' => 3,
    ],

    // По тренажёру и виду сессии (review — повторение, learn — новые,
    // session — сессия суждений / раунд заданий):
    //   sec — темп по умолчанию, пока своей истории нет;
    //   min — меньше стольких карточек сессию не даём (если столько есть);
    //   max — ключ конфига с потолком сессии самого тренажёра.
    'trainers' => [
        App\Service\Trainers\TermsTrainerCard::class => [
            'review' => ['sec' => 20, 'min' => 5, 'max' => 'terms.daily_review_limit'],
            'learn' => ['sec' => 60, 'min' => 3, 'max' => 'terms.batch_size'],
        ],
        App\Service\Trainers\DatesTrainerCard::class => [
            'review' => ['sec' => 15, 'min' => 5, 'max' => 'dates.daily_review_limit'],
            'learn' => ['sec' => 45, 'min' => 3, 'max' => 'dates.batch_size'],
        ],
        App\Service\Trainers\PersonsTrainerCard::class => [
            'review' => ['sec' => 15, 'min' => 5, 'max' => 'persons.daily_review_limit'],
            'learn' => ['sec' => 45, 'min' => 3, 'max' => 'persons.batch_size'],
        ],
        App\Service\Trainers\StatementsTrainerCard::class => [
            'session' => ['sec' => 12, 'min' => 5, 'max' => 'statements.session_size'],
        ],
        App\Service\Trainers\TasksTrainerCard::class => [
            'session' => ['sec' => 60, 'min' => 3, 'max' => 'task_trainer.round_size'],
        ],
    ],
];
