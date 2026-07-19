<?php

declare(strict_types=1);

namespace App\Support;

final class Request
{
    public static function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public static function intFromGet(string $key): ?int
    {
        $value = self::get($key);
        if ($value === null || $value === '') {
            return null;
        }

        $filtered = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $filtered === false ? null : (int) $filtered;
    }

    public static function intFromPost(string $key): ?int
    {
        $value = self::post($key);
        if ($value === null || $value === '') {
            return null;
        }

        $filtered = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $filtered === false ? null : (int) $filtered;
    }
}
