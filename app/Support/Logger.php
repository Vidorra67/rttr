<?php

declare(strict_types=1);

namespace App\Support;

use Throwable;

final class Logger
{
    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    public static function exception(Throwable $exception, array $context = []): void
    {
        self::write('error', $exception->getMessage(), array_merge($context, [
            'exception' => $exception::class,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]));
    }

    private static function write(string $level, string $message, array $context): void
    {
        $dir = storage_path('logs');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $file = $dir . DIRECTORY_SEPARATOR . 'app-' . date('Y-m-d') . '.log';
        $record = [
            'time' => date('c'),
            'level' => $level,
            'message' => $message,
            'context' => self::sanitize($context),
        ];

        file_put_contents($file, json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private static function sanitize(array $context): array
    {
        $blocked = ['password', 'pin', 'token', 'secret', 'csrf', 'api_key'];
        foreach ($context as $key => $value) {
            foreach ($blocked as $needle) {
                if (str_contains(strtolower((string) $key), $needle)) {
                    $context[$key] = '[redacted]';
                    continue 2;
                }
            }
        }
        return $context;
    }
}
