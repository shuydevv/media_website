<?php

/*
 * Собственный чат трансляций (вместо чата Kinescope). Правила — ТЗ «чат
 * трансляций — этап 1»; числа здесь, чтобы не искать их по коду.
 */
return [
    // false — на странице урока iframe чата Kinescope. По умолчанию выключен:
    // до включения чат пробуют в песочнице /live-chat/sandbox (она работает
    // всегда), потом LIVE_CHAT_ENABLED=true.
    'enabled' => env('LIVE_CHAT_ENABLED', false),

    'opens_before_minutes' => 15,   // чат открывается до начала урока по расписанию
    'break_grace_minutes' => 10,    // после «эфир закончился» ещё можно писать
    'fallback_close_hours' => 4,    // нет сигнала Kinescope — закрыть после конца урока
    'stuck_live_hours' => 8,        // «эфир идёт» дольше — считаем, что сигнал конца потерян

    'message_max' => 500,
    'name_max' => 32,
    'initial_messages' => 100,

    'poll_visible_ms' => 2000,
    'poll_hidden_ms' => 15000,

    // Медленный режим (App\Service\LiveChat\FloodGuard).
    'flood' => [
        'burst_count' => 5, 'burst_seconds' => 10, // 5 сообщений за 10 с
        'repeat_count' => 3,                        // или 3 одинаковых подряд
        'slow_seconds' => 30,                       // тогда — раз в 30 с
        'calm_seconds' => 120,                      // снимается через 2 мин без флуда
    ],

    // В записи время без эфира между кусками вычитается — если Kinescope
    // склеивает куски записи без паузы. Проверить на тестовом эфире.
    'replay_skip_gaps' => true,

    // Уведомления Kinescope. Точный формат не проверен (документация не
    // открывалась), поэтому каждое пишется в kinescope_webhook_events, а
    // имена событий — здесь: после тестового эфира поправить по журналу.
    'kinescope' => [
        'webhook_secret' => env('KINESCOPE_WEBHOOK_SECRET'),
        'start_events' => ['live.connected', 'live.enabled', 'live.started', 'live.start', 'live.active'],
        'end_events' => ['live.finished', 'live.disconnected', 'live.ended', 'live.stopped', 'live.end'],
    ],
];
