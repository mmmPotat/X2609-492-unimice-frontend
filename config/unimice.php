<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Подключение к REST API платформы UniMICE
    |--------------------------------------------------------------------------
    |
    | Админка — тонкий клиент: бизнес-данные живут только на стороне API.
    | Значения задаются через переменные окружения, секреты в репозитории
    | не хранятся.
    |
    */

    'base_url' => env('UNIMICE_API_BASE_URL'),

    'auth' => [
        'login_path' => '/auth/login',
        'email' => env('UNIMICE_API_EMAIL'),
        'password' => env('UNIMICE_API_PASSWORD'),
    ],

    'http' => [
        'timeout' => (int) env('UNIMICE_HTTP_TIMEOUT', 15),
        'connect_timeout' => (int) env('UNIMICE_HTTP_CONNECT_TIMEOUT', 5),
        'retry_times' => (int) env('UNIMICE_HTTP_RETRY_TIMES', 2),
    ],

    'token' => [
        // JWT бэкенда живёт 24 часа; кешируем короче, чтобы не ловить 401
        // на границе, и обновляем токен по факту 401.
        'cache_ttl' => (int) env('UNIMICE_TOKEN_CACHE_TTL', 72000),
        'cache_key' => env('UNIMICE_TOKEN_CACHE_KEY', 'unimice.api.token'),
    ],
];
