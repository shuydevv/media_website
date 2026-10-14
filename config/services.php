<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id'   => env('TELEGRAM_CHAT_ID'),
    ],

    // Бот воронки пробного урока (@poltavskiy_ege_bot) — отдельный от бота
    // алертов выше. admin_chat_id — супергруппа с темами, куда бот раскладывает
    // диалоги (см. App\Service\Telegram\AdminTopics), webhook_secret —
    // проверка заголовка X-Telegram-Bot-Api-Secret-Token на входящих апдейтах.
    'telegram_funnel' => [
        'bot_token'      => env('TELEGRAM_FUNNEL_BOT_TOKEN'),
        'admin_chat_id'  => env('TELEGRAM_FUNNEL_ADMIN_CHAT_ID'),
        'webhook_secret' => env('TELEGRAM_FUNNEL_WEBHOOK_SECRET'),
    ],


];
