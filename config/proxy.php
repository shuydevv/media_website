<?php
return [
    'http'    => env('HTTP_PROXY', null),
    'https'   => env('HTTPS_PROXY', null),
    'all'     => env('ALL_PROXY', null),
    'no'      => env('NO_PROXY', 'localhost,127.0.0.1,::1'),
    // если указать путь — Guzzle будет использовать этот файл
    'ca_file' => env('HTTP_CA_FILE', null),
    // fallback, если ca_file не задан
    'verify'  => env('HTTP_VERIFY_SSL', true),
];
