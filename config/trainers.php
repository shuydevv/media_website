<?php

// Тренажёры платформы, которые показываются в блоке с рыбой на дашборде
// (кнопка «Тренажёры» и реплика рыбы). Каждый — класс, реализующий
// App\Service\Trainers\Trainer. Новый тренажёр добавляется сюда.

return [
    'list' => [
        App\Service\Trainers\TermsTrainerCard::class,
    ],
];
