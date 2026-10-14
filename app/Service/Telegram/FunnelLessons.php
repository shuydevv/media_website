<?php

namespace App\Service\Telegram;

use App\Http\Controllers\Main\TrialLessonController;
use App\Models\TelegramContact;

/**
 * Пробные уроки в воронке: код из deep link (cho / er, config
 * telegram_funnel.lessons) → страница /trial/{slug}. Бонус — всегда «другой»
 * урок из двух.
 */
final class FunnelLessons
{
    /** @return string[] */
    public static function codes(): array
    {
        return array_keys(config('telegram_funnel.lessons'));
    }

    public static function exists(string $code): bool
    {
        return array_key_exists($code, config('telegram_funnel.lessons'));
    }

    public static function slug(string $code): string
    {
        return config('telegram_funnel.lessons')[$code];
    }

    public static function title(string $code): string
    {
        return TrialLessonController::LESSONS[self::slug($code)]['title'] ?? $code;
    }

    public static function bonusFor(string $code): string
    {
        foreach (self::codes() as $other) {
            if ($other !== $code) {
                return $other;
            }
        }

        return $code;
    }

    /** Персональная ссылка: открытие засчитывается в LessonLinkController. */
    public static function trackedUrl(TelegramContact $contact, string $code): string
    {
        return route('telegram.lesson-link', ['token' => $contact->link_token, 'lesson' => $code]);
    }
}
