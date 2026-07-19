#!/usr/bin/env php
<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require BASE_PATH . '/app/Support/bootstrap.php';

use App\Services\ScheduledTaskService;

$taskKey = $argv[1] ?? '';
if ($taskKey === '') {
    fwrite(STDERR, "Usage: php scripts/cron/run_task.php <task_key>\n");
    exit(2);
}

try {
    $result = (new ScheduledTaskService())->runCli($taskKey);
    $line = strtoupper((string) ($result['status'] ?? 'error')) . ' ' . $taskKey;
    if (!empty($result['output'])) {
        $line .= ' ' . $result['output'];
    }
    if (!empty($result['error'])) {
        $line .= ' error="' . $result['error'] . '"';
    }
    echo $line . PHP_EOL;
    exit(($result['status'] ?? '') === 'ok' || ($result['status'] ?? '') === 'skipped' ? 0 : 1);
} catch (Throwable $exception) {
    fwrite(STDERR, 'ERROR ' . $taskKey . ' message="' . $exception->getMessage() . '"' . PHP_EOL);
    exit(1);
}
