<?php

namespace App\Http\Controllers\Main;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Telegram\TrialFeedbackController;
use App\Models\TelegramContact;
use App\Service\Telegram\FunnelTexts;
use Illuminate\Http\Request;

class TrialLessonController extends Controller
{
    // Пробные уроки — публичные страницы без входа. Их всего пара штук и
    // меняются они редко, поэтому список живёт здесь, а не в БД: slug из
    // адреса → заголовок и id видео в Kinescope. Публичная — заголовки
    // отсюда же берёт бот воронки (App\Service\Telegram\FunnelLessons).
    public const LESSONS = [
        // cover — обложка для превью ссылки (og:image): Telegram и другие
        // мессенджеры рисуют по ней карточку, в т.ч. в сообщениях бота воронки.
        'ekonomicheskiy-rost' => [
            'title' => 'Экономический рост',
            'video_id' => '0cELmsWinCGo2LybNuV3hU',
            'cover' => 'img/trial/ekonomicheskiy-rost.jpg',
        ],
        'chelovek-i-obshchestvo' => [
            'title' => 'Человек и общество',
            'video_id' => 'nojZ85c5ubHt725cenBuYC',
            'cover' => 'img/trial/chelovek-i-obshchestvo.jpg',
        ],
    ];

    public function __invoke(Request $request, string $slug)
    {
        $lesson = self::LESSONS[$slug] ?? abort(404);

        // Пришёл из бота воронки (cookie персональной ссылки) — под видео
        // оценка урока, а плеер шлёт отметки просмотра. Иначе страница обычная.
        $feedback = null;
        if ($found = TrialFeedbackController::contactFor($request, $slug)) {
            [$contact, $bonus] = $found;
            $feedback = [
                'rating' => $contact->slot($bonus, 'rating'),
                'buttons' => collect(TelegramContact::RATINGS)->mapWithKeys(fn ($r) => [$r => FunnelTexts::plain('btn_'.$r)])->all(),
                'rate_url' => route('telegram.trial.rate', $slug),
                'progress_url' => route('telegram.trial.progress', $slug),
                // «Продолжить с того же места» из бота (?t=секунды).
                'start_at' => max(0, $request->integer('t')),
            ];
        }

        return view('main.trial-lesson', ['lesson' => $lesson, 'feedback' => $feedback]);
    }
}
