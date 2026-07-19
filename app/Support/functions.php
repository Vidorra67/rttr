<?php

declare(strict_types=1);

function base_path(string $path = ''): string
{
    $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
    return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
}

function storage_path(string $path = ''): string
{
    return base_path('storage' . ($path === '' ? '' : DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR)));
}

function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($value === false || $value === null) {
        return $default;
    }
    return $value;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
