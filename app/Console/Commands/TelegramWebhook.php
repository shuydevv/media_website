<?php

namespace App\Console\Commands;

use App\Service\Telegram\TelegramApiException;
use App\Service\Telegram\TelegramBots;
use Illuminate\Console\Command;

/**
 * Регистрация webhook-а бота (TelegramBots: lesson — воронка, по умолчанию;
 * diag — диагностика) в Telegram. Запускается руками один раз на сервере (и
 * повторно — если поменялся домен или секрет). Адрес берётся из APP_URL,
 * поэтому там должен быть боевой https-домен.
 */
class TelegramWebhook extends Command
{
    protected $signature = 'telegram:webhook {bot=lesson : ключ бота — lesson или diag} {--info : только показать текущее состояние} {--delete : снять webhook}';

    protected $description = 'Зарегистрировать webhook бота (воронки @poltavskiy_ege_bot или диагностики) в Telegram';

    public function handle(): int
    {
        $bot = (string) $this->argument('bot');
        if (! TelegramBots::exists($bot)) {
            $this->error("Нет бота {$bot}. Есть: ".implode(', ', TelegramBots::keys()));

            return self::FAILURE;
        }

        $api = TelegramBots::api($bot);
        if (! $api->isConfigured()) {
            $this->error(TelegramBots::env($bot, 'BOT_TOKEN').' не задан в .env');

            return self::FAILURE;
        }

        try {
            if ($this->option('delete')) {
                $api->call('deleteWebhook');
                $this->info('Webhook снят.');
            } elseif (! $this->option('info')) {
                $secret = TelegramBots::webhookSecret($bot);
                if ($secret === '') {
                    $this->error(TelegramBots::env($bot, 'WEBHOOK_SECRET').' не задан в .env');

                    return self::FAILURE;
                }

                // Бот воронки — по адресу без ключа: так он зарегистрирован.
                $url = route('telegram.webhook', $bot === TelegramBots::LESSON ? [] : ['bot' => $bot]);
                $api->call('setWebhook', [
                    'url' => $url,
                    'secret_token' => $secret,
                    'allowed_updates' => ['message', 'edited_message', 'callback_query', 'my_chat_member'],
                    'max_connections' => 10,
                ]);
                $this->info("Webhook установлен: {$url}");

                $profile = config($bot === TelegramBots::LESSON ? 'telegram_funnel.profile' : 'telegram_diag.profile');
                $api->call('setMyDescription', ['description' => $profile['description']]);
                $api->call('setMyShortDescription', ['short_description' => $profile['short_description']]);
                $this->info('Описание бота обновлено.');
            }

            $me = $api->call('getMe');
            $info = $api->call('getWebhookInfo');
        } catch (TelegramApiException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('Бот: @'.$me['username']);
        $this->line('URL: '.($info['url'] ?: '—'));
        $this->line('В очереди у Telegram: '.($info['pending_update_count'] ?? 0));
        if (! empty($info['last_error_message'])) {
            $this->warn('Последняя ошибка доставки: '.$info['last_error_message'].' ('.date('d.m.Y H:i', $info['last_error_date']).')');
        }
        $this->line('Админ-группа: '.(config('services.telegram_funnel.admin_chat_id') ?: 'не задана — напишите /chatid в группе'));

        return self::SUCCESS;
    }
}
