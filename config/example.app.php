<?php

declare(strict_types=1);

return [
    'name' => 'Ritterlager Manager',
    'environment' => env('APP_ENV', 'local'),
    'debug' => filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL),
    'base_url' => env('APP_BASE_URL', ''),
    'timezone' => env('APP_TIMEZONE', 'Europe/Berlin'),
    'session' => [
        'name' => 'ritterlager_session',
        'timeout_minutes' => 120,
    ],
    'security' => [
        'login_max_attempts' => 5,
        'login_lock_minutes' => 15,
    ],
];
