<?php

namespace App\Http\Controllers\Main;

use App\Http\Controllers\Controller;

class TrialLessonController extends Controller
{
    // Пробные уроки — публичные страницы без входа. Их всего пара штук и
    // меняются они редко, поэтому список живёт здесь, а не в БД: slug из
    // адреса → заголовок и id видео в Kinescope. Публичная — заголовки
    // отсюда же берёт бот воронки (App\Service\Telegram\FunnelLessons).
    public const LESSONS = [
        'ekonomicheskiy-rost' => [
            'title' => 'Экономический рост',
            'video_id' => '0cELmsWinCGo2LybNuV3hU',
        ],
        'chelovek-i-obshchestvo' => [
            'title' => 'Человек и общество',
            'video_id' => 'nojZ85c5ubHt725cenBuYC',
        ],
    ];

    public function __invoke(string $slug)
    {
        $lesson = self::LESSONS[$slug] ?? abort(404);

        return view('main.trial-lesson', ['lesson' => $lesson]);
    }
}
