<?php

declare(strict_types=1);

namespace App\Support;

final class Config
{
    private static array $cache = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $file = array_shift($segments);
        if ($file === null || $file === '') {
            return $default;
        }

        $data = self::load($file);
        foreach ($segments as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return $default;
            }
            $data = $data[$segment];
        }

        return $data;
    }

    public static function load(string $file): array
    {
        if (array_key_exists($file, self::$cache)) {
            return self::$cache[$file];
        }

        $path = base_path('config/' . $file . '.php');
        if (!is_file($path)) {
            self::$cache[$file] = [];
            return [];
        }

        $data = require $path;
        self::$cache[$file] = is_array($data) ? $data : [];
        return self::$cache[$file];
    }
}
