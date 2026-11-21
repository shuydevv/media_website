<?php

namespace App\Console\Commands;

use App\Models\AvitoAccount;
use App\Service\Avito\AvitoApiException;
use Illuminate\Console\Command;

/**
 * Подписка аккаунтов Авито на webhook мессенджера. Обычно не нужна: аккаунт
 * подписывается сам при добавлении в админке («Telegram-бот → Авито»).
 * Руками — после смены домена или AVITO_WEBHOOK_SECRET (тогда сначала
 * --delete со старым секретом). Адрес — из APP_URL, общий для всех аккаунтов.
 */
class AvitoWebhook extends Command
{
    protected $signature = 'avito:webhook {--info : только показать подписки} {--delete : отписать этот адрес}';

    protected $description = 'Подписать аккаунты Авито на сообщения мессенджера (тема «🛒 Авито» в админ-группе)';

    public function handle(): int
    {
        $accounts = AvitoAccount::orderBy('id')->get();
        if ($accounts->isEmpty()) {
            $this->error('Аккаунтов Авито нет — добавьте в админке: Telegram-бот → Авито.');

            return self::FAILURE;
        }

        $secret = (string) config('services.avito.webhook_secret');
        if ($secret === '' && ! $this->option('info')) {
            $this->error('AVITO_WEBHOOK_SECRET не задан в .env');

            return self::FAILURE;
        }

        $failed = false;
        foreach ($accounts as $account) {
            $this->line("<info>{$account->name}</info> (client_id {$account->client_id})");

            if (! $this->option('info')) {
                if ($this->option('delete')) {
                    $account->disconnect();
                    $account->forceFill(['subscribed_at' => null])->save();
                    $this->line('  отписано');
                } elseif ($account->connect()) {
                    $this->line('  подписано');
                } else {
                    $this->error('  '.$account->last_error);
                    $failed = true;

                    continue;
                }
            }

            try {
                $self = $account->api()->self();
                $subscriptions = $account->api()->subscriptions();
            } catch (AvitoApiException $e) {
                $this->error('  '.$e->getMessage());
                $failed = true;

                continue;
            }

            $this->line('  профиль: '.($self['name'] ?? '—').' (id '.($self['id'] ?? '—').')');
            foreach ($subscriptions as $s) {
                // Секрет в адресе не печатаем — вывод может уйти в логи деплоя.
                $shown = $secret !== '' ? str_replace($secret, '***', (string) $s['url']) : (string) $s['url'];
                $this->line('  подписка: '.$shown.' ('.($s['version'] ?? '?').')');
            }
            if ($subscriptions === []) {
                $this->line('  подписок нет');
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
