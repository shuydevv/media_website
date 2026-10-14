<?php

namespace App\Console\Commands;

use App\Service\Telegram\TelegramApi;
use App\Service\Telegram\TelegramApiException;
use Illuminate\Console\Command;

/**
 * Регистрация webhook-а бота воронки в Telegram. Запускается руками один раз
 * на сервере (и повторно — если поменялся домен или секрет). Адрес берётся
 * из APP_URL, поэтому там должен быть боевой https-домен.
 */
class TelegramWebhook extends Command
{
    protected $signature = 'telegram:webhook {--info : только показать текущее состояние} {--delete : снять webhook}';

    protected $description = 'Зарегистрировать webhook бота воронки (@poltavskiy_ege_bot) в Telegram';

    public function handle(): int
    {
        $api = TelegramApi::funnel();
        if (! $api->isConfigured()) {
            $this->error('TELEGRAM_FUNNEL_BOT_TOKEN не задан в .env');

            return self::FAILURE;
        }

        try {
            if ($this->option('delete')) {
                $api->call('deleteWebhook');
                $this->info('Webhook снят.');
            } elseif (! $this->option('info')) {
                $secret = (string) config('services.telegram_funnel.webhook_secret');
                if ($secret === '') {
                    $this->error('TELEGRAM_FUNNEL_WEBHOOK_SECRET не задан в .env');

                    return self::FAILURE;
                }

                $url = route('telegram.webhook');
                $api->call('setWebhook', [
                    'url' => $url,
                    'secret_token' => $secret,
                    'allowed_updates' => ['message', 'callback_query', 'my_chat_member'],
                    'max_connections' => 10,
                ]);
                $this->info("Webhook установлен: {$url}");

                $api->call('setMyDescription', ['description' => config('telegram_funnel.profile.description')]);
                $api->call('setMyShortDescription', ['short_description' => config('telegram_funnel.profile.short_description')]);
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
