<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Models\TelegramContact;
use App\Service\Telegram\FunnelBot;
use App\Service\Telegram\FunnelLessons;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Обратная связь со страницы пробного урока от тех, кто пришёл из бота
 * (cookie от LessonLinkController): оценка под видео и отметки плеера.
 * Без cookie — 404: обычного посетителя сайта с Telegram не связать.
 */
class TrialFeedbackController extends Controller
{
    /** Оценка под видео; можно переголосовать — считается последняя. */
    public function rate(Request $request, string $slug)
    {
        [$contact, $bonus] = $this->resolve($request, $slug);
        $data = $request->validate(['rating' => ['required', Rule::in(TelegramContact::RATINGS)]]);

        FunnelBot::make()->applyRating($contact, $bonus, $data['rating'], 'site');

        return response()->json(['ok' => true, 'rating' => $data['rating']]);
    }

    /** Отметка плеера (main/trial-lesson): раз в ~30 с, пока видео играет. */
    public function progress(Request $request, string $slug)
    {
        [$contact, $bonus] = $this->resolve($request, $slug);
        $data = $request->validate([
            'seconds' => ['required', 'integer', 'min:0'],
            'position' => ['nullable', 'integer', 'min:0'],
            'duration' => ['nullable', 'integer', 'min:0'],
        ]);

        FunnelBot::make()->trackProgress($contact, $bonus, (int) $data['seconds'], (int) ($data['position'] ?? 0), (int) ($data['duration'] ?? 0));

        return response()->json(['ok' => true]);
    }

    /**
     * Контакт по cookie и какой это для него урок: основной (из рекламы) или бонусный.
     *
     * @return array{0: TelegramContact, 1: bool}
     */
    public static function contactFor(Request $request, string $slug): ?array
    {
        $code = FunnelLessons::codeForSlug($slug);
        $token = (string) $request->cookie(LessonLinkController::COOKIE);
        if ($code === null || $token === '') {
            return null;
        }

        $contact = TelegramContact::where('link_token', $token)->first();

        return $contact ? [$contact, $code !== $contact->lesson] : null;
    }

    private function resolve(Request $request, string $slug): array
    {
        return self::contactFor($request, $slug) ?? abort(404);
    }
}
