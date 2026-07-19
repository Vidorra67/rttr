<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = base_path('app/' . str_replace('\\', '/', $relative) . '.php');

    if (is_file($file)) {
        require_once $file;
    }
});

$timezone = \App\Support\Config::get('app.timezone', 'Europe/Berlin');
date_default_timezone_set((string) $timezone);

if (\App\Support\Config::get('app.debug', false) === true) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
}

\App\Support\Session::start();
