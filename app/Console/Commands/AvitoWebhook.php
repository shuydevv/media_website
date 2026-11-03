<?php

namespace App\Console\Commands;

use App\Service\Avito\AvitoApi;
use App\Service\Avito\AvitoApiException;
use Illuminate\Console\Command;

/**
 * Подписка на webhook мессенджера Авито. Запускается руками один раз на
 * сервере (и повторно — если поменялся домен или AVITO_WEBHOOK_SECRET:
 * тогда сначала --delete со старым секретом). Адрес — из APP_URL.
 */
class AvitoWebhook extends Command
{
    protected $signature = 'avito:webhook {--info : только показать подписки} {--delete : отписать этот адрес}';

    protected $description = 'Подписать сайт на сообщения мессенджера Авито (тема «🛒 Авито» в админ-группе)';

    public function handle(): int
    {
        $api = new AvitoApi();
        if (! $api->isConfigured()) {
            $this->error('AVITO_CLIENT_ID / AVITO_CLIENT_SECRET не заданы в .env');

            return self::FAILURE;
        }

        $secret = (string) config('services.avito.webhook_secret');
        if ($secret === '' && ! $this->option('info')) {
            $this->error('AVITO_WEBHOOK_SECRET не задан в .env');

            return self::FAILURE;
        }
        $url = route('avito.webhook', ['secret' => $secret ?: 'x']);

        try {
            if ($this->option('delete')) {
                $api->unsubscribe($url);
                $this->info('Отписано.');
            } elseif (! $this->option('info')) {
                $api->subscribe($url);
                $this->info('Подписано: '.route('avito.webhook', ['secret' => '***']));
            }

            $self = $api->self();
            $subscriptions = $api->subscriptions();
        } catch (AvitoApiException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('Аккаунт: '.($self['name'] ?? '—').' (id '.($self['id'] ?? '—').')');
        $this->line('Подписки:');
        foreach ($subscriptions as $s) {
            // Секрет в адресе не печатаем — вывод может уйти в логи деплоя.
            $shown = $secret !== '' ? str_replace($secret, '***', (string) $s['url']) : (string) $s['url'];
            $this->line('  '.$shown.' ('.($s['version'] ?? '?').')');
        }
        if ($subscriptions === []) {
            $this->line('  —');
        }

        return self::SUCCESS;
    }
}
