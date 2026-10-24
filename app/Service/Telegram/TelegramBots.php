<?php

namespace App\Service\Telegram;

/**
 * Боты платформы. У каждого свой токен, свой webhook
 * (/api/telegram/webhook/{bot}) и свой сценарий (BotScenario); контакты —
 * telegram_contacts.bot, один человек в двух ботах — два контакта.
 * Админ-группа у всех одна (services.telegram_funnel.admin_chat_id).
 *
 *   lesson — воронка пробного урока (@poltavskiy_ege_bot, FunnelBot);
 *   diag   — диагностика по обществознанию (DiagBot, ТЗ — docs/diagnostic/).
 *
 * Бот воронки — «главный» в админ-группе: только он отвечает на команды
 * там (/chatid, /emoji), иначе на каждую команду пришло бы по ответу от
 * каждого бота. Служебные темы у каждого свои (у диагностики — «🧪 Диагностики»).
 */
final class TelegramBots
{
    public const LESSON = 'lesson';

    public const DIAG = 'diag';

    /** Ключ бота → блок config/services.php и префикс его переменных в .env. */
    private const BOTS = [
        self::LESSON => ['config' => 'services.telegram_funnel', 'env' => 'TELEGRAM_FUNNEL'],
        self::DIAG => ['config' => 'services.telegram_diag', 'env' => 'TELEGRAM_DIAG'],
    ];

    /** @return string[] */
    public static function keys(): array
    {
        return array_keys(self::BOTS);
    }

    public static function exists(string $bot): bool
    {
        return isset(self::BOTS[$bot]);
    }

    public static function token(string $bot): ?string
    {
        return config(self::BOTS[$bot]['config'].'.bot_token');
    }

    public static function webhookSecret(string $bot): string
    {
        return (string) config(self::BOTS[$bot]['config'].'.webhook_secret');
    }

    /** Имя переменной .env — для понятных ошибок («TELEGRAM_DIAG_BOT_TOKEN не задан»). */
    public static function env(string $bot, string $suffix): string
    {
        return self::BOTS[$bot]['env'].'_'.$suffix;
    }

    public static function api(string $bot): TelegramApi
    {
        return TelegramApi::forBot($bot);
    }

    /** Сценарий бота; null — бот заведён, но сценарий ещё не подключён. */
    public static function scenario(string $bot): ?BotScenario
    {
        return match ($bot) {
            self::LESSON => FunnelBot::make(),
            self::DIAG => DiagBot::make(),
            default => null,
        };
    }

    /** @return array<string, BotScenario> боты с подключённым сценарием */
    public static function scenarios(): array
    {
        $all = [];
        foreach (self::keys() as $bot) {
            if ($scenario = self::scenario($bot)) {
                $all[$bot] = $scenario;
            }
        }

        return $all;
    }
}
