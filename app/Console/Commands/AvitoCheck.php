<?php

namespace App\Console\Commands;

use App\Models\AvitoAccount;
use App\Service\Avito\AvitoApiException;
use App\Service\Telegram\AdminTopics;
use App\Service\Telegram\TelegramApi;
use App\Service\Telegram\TelegramApiException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Раз в день: живы ли аккаунты Авито. Кончился тариф или отозвали ключи —
 * сообщения просто перестают приходить, и без проверки этого не заметить.
 * Пропала подписка на webhook — подписываем заново сами. Что починить не
 * вышло — в «⚠️ Сбои» (каждый день, пока не починят) и в last_error для
 * админки.
 */
class AvitoCheck extends Command
{
    protected $signature = 'avito:check';

    protected $description = 'Проверить аккаунты Авито (ключи, тариф, подписка) и сообщить о сбоях в «⚠️ Сбои»';

    public function handle(): int
    {
        $problems = [];

        foreach (AvitoAccount::orderBy('id')->get() as $account) {
            $error = $this->check($account);
            $account->forceFill(['last_error' => $error])->save();

            if ($error) {
                $problems[] = '• <b>'.e($account->name).'</b>: '.e(mb_substr($error, 0, 300));
                $this->error("{$account->name}: {$error}");
            } else {
                $this->line("{$account->name}: ок");
            }
        }

        if ($problems) {
            $this->postAlert($problems);
        }

        return $problems ? self::FAILURE : self::SUCCESS;
    }

    private function check(AvitoAccount $account): ?string
    {
        try {
            $account->refreshProfile();
            $url = AvitoAccount::webhookUrl();
            $subscribed = collect($account->api()->subscriptions())->contains(fn ($s) => ($s['url'] ?? null) === $url);
        } catch (AvitoApiException $e) {
            return $e->getMessage();
        }

        if ($subscribed) {
            return null;
        }

        // Подписку сняли (или Авито её сбросил) — переподписываем.
        if ($account->connect()) {
            Log::info('Avito: аккаунт переподписан на webhook', ['account' => $account->id]);

            return null;
        }

        return 'подписка на сообщения пропала, переподписать не вышло: '.$account->last_error;
    }

    private function postAlert(array $problems): void
    {
        $html = "⚠️ <b>Авито: сообщения с аккаунтов могут не приходить</b>\n".implode("\n", $problems)
            ."\n\nЧаще всего — кончился тариф с API мессенджера или сменились ключи. "
            .'<a href="'.e(route('admin.telegram.avito.index')).'">Аккаунты Авито</a>';

        try {
            (new AdminTopics(TelegramApi::funnel()))->postToService('alerts', $html, false);
        } catch (TelegramApiException $e) {
            Log::error('Avito: не удалось сообщить о сбое аккаунтов', ['error' => $e->getMessage()]);
        }
    }
}
