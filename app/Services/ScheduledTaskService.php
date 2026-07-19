<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Auth;
use App\Support\Database;
use App\Support\Logger;
use PDO;
use RuntimeException;
use Throwable;

final class ScheduledTaskService
{
    private const FIXED_TASKS = [
        'backup_daily' => 'Tägliches Backup',
        'backup_weekly' => 'Wöchentliches Backup',
        'cleanup_logs' => 'Logs bereinigen',
    ];

    public function __construct(
        private readonly AuditService $auditService = new AuditService(),
        private readonly BackupService $backupService = new BackupService()
    ) {
    }

    public function tasks(): array
    {
        $stmt = Database::connection()->query("SELECT * FROM scheduled_tasks ORDER BY task_key ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function runs(int $limit = 80): array
    {
        $stmt = Database::connection()->prepare("SELECT r.*, t.task_key, t.label
            FROM scheduled_task_runs r
            JOIN scheduled_tasks t ON t.id = r.task_id
            ORDER BY r.started_at DESC, r.id DESC
            LIMIT :limit");
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function regenerateToken(int $taskId): array
    {
        $task = $this->findTaskById($taskId);
        if ($task === null) {
            throw new RuntimeException('Aufgabe wurde nicht gefunden.');
        }
        $token = bin2hex(random_bytes(32));
        $stmt = Database::connection()->prepare('UPDATE scheduled_tasks SET token_hash = :token_hash, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            'token_hash' => password_hash($token, PASSWORD_DEFAULT),
            'id' => $taskId,
        ]);
        $this->auditService->record('scheduled_tasks.token_regenerated', $this->currentUserId(), 'scheduled_task', $taskId, [
            'task_key' => (string) $task['task_key'],
        ]);
        return ['task' => $task, 'token' => $token];
    }

    public function setActive(int $taskId, bool $active): void
    {
        $task = $this->findTaskById($taskId);
        if ($task === null) {
            throw new RuntimeException('Aufgabe wurde nicht gefunden.');
        }
        $stmt = Database::connection()->prepare('UPDATE scheduled_tasks SET is_active = :is_active, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['is_active' => $active ? 1 : 0, 'id' => $taskId]);
        $this->auditService->record('scheduled_tasks.updated', $this->currentUserId(), 'scheduled_task', $taskId, [
            'task_key' => (string) $task['task_key'],
            'is_active' => $active,
        ]);
    }

    public function runById(int $taskId, string $triggeredBy = 'admin'): array
    {
        $task = $this->findTaskById($taskId);
        if ($task === null) {
            throw new RuntimeException('Aufgabe wurde nicht gefunden.');
        }
        return $this->runTask($task, $triggeredBy);
    }

    public function runHttp(string $taskKey, string $token): array
    {
        $task = $this->findTaskByKey($taskKey);
        if ($task === null) {
            throw new RuntimeException('Aufgabe wurde nicht gefunden.');
        }
        $hash = (string) ($task['token_hash'] ?? '');
        if ($hash === '' || $token === '' || !password_verify($token, $hash)) {
            throw new RuntimeException('Cron-Token ist ungültig.');
        }
        return $this->runTask($task, 'http');
    }

    public function runCli(string $taskKey): array
    {
        $task = $this->findTaskByKey($taskKey);
        if ($task === null) {
            throw new RuntimeException('Aufgabe wurde nicht gefunden.');
        }
        return $this->runTask($task, 'cli');
    }

    private function runTask(array $task, string $triggeredBy): array
    {
        $taskKey = (string) $task['task_key'];
        if (!array_key_exists($taskKey, self::FIXED_TASKS)) {
            throw new RuntimeException('Diese Aufgabe ist nicht erlaubt.');
        }
        if ((int) ($task['is_active'] ?? 0) !== 1 && $triggeredBy !== 'admin') {
            throw new RuntimeException('Diese Aufgabe ist deaktiviert.');
        }

        $pdo = Database::connection();
        $taskId = (int) $task['id'];
        $lockStmt = $pdo->prepare("UPDATE scheduled_tasks
            SET locked_until = DATE_ADD(NOW(), INTERVAL 30 MINUTE)
            WHERE id = :id AND (locked_until IS NULL OR locked_until < NOW())");
        $lockStmt->execute(['id' => $taskId]);
        if ($lockStmt->rowCount() !== 1) {
            return $this->recordRun($taskId, 'skipped', $triggeredBy, 'SKIPPED ' . $taskKey . ' locked', null, 0, 0);
        }

        $started = microtime(true);
        $processed = 0;
        $output = '';
        try {
            if ($taskKey === 'backup_daily') {
                $this->backupService->create('daily', $this->currentUserId());
                $processed = 1;
                $output = 'OK backup_daily processed=1';
            } elseif ($taskKey === 'backup_weekly') {
                $this->backupService->create('weekly', $this->currentUserId());
                $processed = 1;
                $output = 'OK backup_weekly processed=1';
            } elseif ($taskKey === 'cleanup_logs') {
                $processed = $this->cleanupLogs();
                $output = 'OK cleanup_logs processed=' . $processed;
            }

            $durationMs = (int) round((microtime(true) - $started) * 1000);
            $result = $this->recordRun($taskId, 'ok', $triggeredBy, $output, null, 0, $processed, $durationMs);
            $this->releaseLock($taskId, 'ok');
            return $result;
        } catch (Throwable $exception) {
            $durationMs = (int) round((microtime(true) - $started) * 1000);
            $result = $this->recordRun($taskId, 'error', $triggeredBy, 'ERROR ' . $taskKey, $exception->getMessage(), 1, $processed, $durationMs);
            $this->releaseLock($taskId, 'error');
            Logger::error('Scheduled task failed', ['task_key' => $taskKey, 'message' => $exception->getMessage()]);
            return $result;
        }
    }

    private function recordRun(int $taskId, string $status, string $triggeredBy, string $output, ?string $error, int $exitCode, int $processed, int $durationMs = 0): array
    {
        $stmt = Database::connection()->prepare("INSERT INTO scheduled_task_runs
            (task_id, status, started_at, finished_at, duration_ms, exit_code, processed_count, output_text, error_text, triggered_by, created_at)
            VALUES (:task_id, :status, NOW(), NOW(), :duration_ms, :exit_code, :processed_count, :output_text, :error_text, :triggered_by, NOW())");
        $stmt->execute([
            'task_id' => $taskId,
            'status' => $status,
            'duration_ms' => $durationMs,
            'exit_code' => $exitCode,
            'processed_count' => $processed,
            'output_text' => $output,
            'error_text' => $error,
            'triggered_by' => $triggeredBy,
        ]);
        $runId = (int) Database::connection()->lastInsertId();
        return ['run_id' => $runId, 'status' => $status, 'output' => $output, 'error' => $error, 'processed' => $processed];
    }

    private function releaseLock(int $taskId, string $status): void
    {
        $stmt = Database::connection()->prepare("UPDATE scheduled_tasks
            SET locked_until = NULL, last_run_at = NOW(), last_status = :last_status, updated_at = NOW()
            WHERE id = :id");
        $stmt->execute(['last_status' => $status, 'id' => $taskId]);
    }

    private function cleanupLogs(): int
    {
        $retentionDays = 30;
        $deleted = 0;
        foreach (glob(storage_path('logs/*.log')) ?: [] as $file) {
            if (!is_file($file)) {
                continue;
            }
            $mtime = filemtime($file) ?: time();
            if ($mtime < strtotime('-' . $retentionDays . ' days')) {
                @unlink($file);
                $deleted++;
            }
        }
        return $deleted;
    }

    private function findTaskById(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM scheduled_tasks WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function findTaskByKey(string $key): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM scheduled_tasks WHERE task_key = :task_key LIMIT 1');
        $stmt->execute(['task_key' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function currentUserId(): ?int
    {
        $user = Auth::user();
        return $user === null ? null : (int) $user['user_id'];
    }
}
