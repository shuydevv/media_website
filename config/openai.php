<?php

return [
    'key'      => env('OPENAI__KEY'),
    'model'    => env('OPENAI_MODEL', 'gpt-5'),
    'base'     => env('OPENAI_BASE', 'https://api.openai.com'),
    'enabled'  => env('OPENAI_ENABLED', true),
    'use_mock' => env('OPENAI_USE_MOCK', false),
    'verify'   => env('OPENAI_VERIFY', true),

    // прокси может быть пустым — тогда не используем
    'proxy'    => env('OPENAI_PROXY', env('HTTPS_PROXY', env('HTTP_PROXY', null))),
];
