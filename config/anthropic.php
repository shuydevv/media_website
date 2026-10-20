<?php

/**
 * Claude API — ИИ-черновик проверки письменных заданий для куратора
 * (см. App\Service\Homework\ClaudeGrader). Правила проверки — в
 * resources/ai/grading/*.md, а не здесь: их правят методисты, не код.
 */
return [
    'key'     => env('ANTHROPIC_API_KEY'),
    'model'   => env('ANTHROPIC_MODEL', 'claude-sonnet-5-5'),

    // low | medium | high | xhigh | max. Глубина рассуждения модели — главный
    // рычаг «качество ↔ цена»: рассуждения оплачиваются как выходные токены,
    // самые дорогие. Задаём явно — у разных моделей разные умолчания.
    'effort'  => env('ANTHROPIC_EFFORT', 'high'),

    'timeout' => (int) env('ANTHROPIC_TIMEOUT', 180),

    // Anthropic API недоступен из РФ напрямую — на проде нужен прокси
    // (http://user:pass@host:port) или свой relay через ANTHROPIC_BASE_URL.
    'proxy'    => env('ANTHROPIC_PROXY'),
    'base_url' => env('ANTHROPIC_BASE_URL'),

    // Проверка SSL: true, false или путь к CA-бандлу. Локальный PHP на
    // Windows часто без бандла (cURL error 60) — укажите путь к cacert.pem,
    // а не выключайте проверку.
    'verify'   => env('ANTHROPIC_VERIFY', true),

    // Пусто/выключено — кнопка «AI-проверка» отвечает понятной ошибкой,
    // а не падает.
    'enabled' => (bool) env('ANTHROPIC_ENABLED', true),
];
