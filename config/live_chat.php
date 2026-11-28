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

    // Эмоции учеников («Аня чувствует: 🗿 База · Поддержать»), зумерские.
    // Картинки — Noto svg в
    // public/img/chat-reactions/noto/<code>.svg.
    'emotions' => [
        // половина — сигналы преподавателю, половина — для настроения
        'lol' => ['😂', 'Ору', '1f602'],
        'vibe_up' => ['😎', '+вайб', '1f60e'],
        'vibe_down' => ['💀', '-вайб', '1f480'],
        'cringe' => ['😬', 'Кринж', '1f62c'],
        'based' => ['🗿', 'База', '1f5ff'],
        'imba' => ['🔥', 'Имба', '1f525'],
        'brain' => ['🤯', 'Мозг всё', '1f92f'],
        'lost' => ['🤔', 'Не вкурил', '1f914'],
        'slow' => ['🐢', 'Не успеваю', '1f422'],
        'break' => ['☕', 'Го перерыв', '2615'],
    ],
    'emotion_level' => 3,        // с какого уровня акулы можно делиться эмоцией
    'emotion_seconds' => 60,     // сколько эмоция висит
    'emotion_cooldown' => 300,   // одна эмоция от ученика раз в 5 минут
    'emotion_max' => 3,          // сколько эмоций на экране одновременно
    'emoji_name_level' => 4,     // с какого уровня можно эмодзи в имени

    // «Пон / Не пон»: сколько идёт опрос и сколько итог висит над лентой.
    'poll_seconds' => 30,
    'poll_result_seconds' => 12,

    // Онлайн: страница отмечается раз в 20 с, «в чате» — кто отмечался за 45 с.
    'presence_seconds' => 45,

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
