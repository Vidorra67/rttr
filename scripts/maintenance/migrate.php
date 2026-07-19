#!/usr/bin/env php
<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require BASE_PATH . '/app/Support/bootstrap.php';

use App\Support\Database;
use App\Support\Logger;
use App\Support\MigrationRunner;

try {
    $runner = new MigrationRunner(Database::connection(), BASE_PATH . '/database/migrations');
    $executed = $runner->run();

    if ($executed === []) {
        echo "OK no migrations pending" . PHP_EOL;
        exit(0);
    }

    foreach ($executed as $migration) {
        echo "OK migrated {$migration}" . PHP_EOL;
    }
    exit(0);
} catch (Throwable $exception) {
    Logger::exception($exception, ['script' => 'migrate']);
    fwrite(STDERR, 'ERROR migration failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
