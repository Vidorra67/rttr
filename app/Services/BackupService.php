<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Auth;
use App\Support\Config;
use App\Support\Database;
use App\Support\Logger;
use PDO;
use RuntimeException;
use Throwable;

final class BackupService
{
    private const INCLUDED_STORAGE_DIRS = ['uploads', 'imports', 'documents'];

    public function __construct(private readonly AuditService $auditService = new AuditService())
    {
    }

    public function runs(int $limit = 80): array
    {
        $stmt = Database::connection()->prepare("SELECT br.*, p.display_name AS created_by_name
            FROM backup_runs br
            LEFT JOIN users u ON u.id = br.created_by
            LEFT JOIN persons p ON p.id = u.person_id
            ORDER BY br.started_at DESC, br.id DESC
            LIMIT :limit");
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findRun(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM backup_runs WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function create(string $type = 'manual', ?int $createdBy = null): array
    {
        $allowed = ['daily', 'weekly', 'monthly', 'quarterly', 'manual'];
        if (!in_array($type, $allowed, true)) {
            $type = 'manual';
        }

        $lockPath = storage_path('temp/backup.lock');
        $lockHandle = fopen($lockPath, 'c+');
        if ($lockHandle === false) {
            throw new RuntimeException('Backup-Lock konnte nicht geöffnet werden.');
        }
        if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
            fclose($lockHandle);
            throw new RuntimeException('Es läuft bereits ein Backup.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare("INSERT INTO backup_runs
            (backup_type, status, started_at, includes_database, includes_files, includes_config, includes_uploads, includes_documents, webdav_status, created_by, created_at)
            VALUES (:backup_type, 'running', NOW(), 1, 1, 1, 1, 1, 'not_configured', :created_by, NOW())");
        $stmt->execute([
            'backup_type' => $type,
            'created_by' => $createdBy ?? $this->currentUserId(),
        ]);
        $runId = (int) $pdo->lastInsertId();
        $started = microtime(true);

        try {
            $backupDir = storage_path('backups/' . date('Y') . '/' . date('m'));
            if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
                throw new RuntimeException('Backup-Ordner konnte nicht erstellt werden.');
            }

            $baseName = 'ritterlager_' . $type . '_' . date('Y-m-d_His');
            $sqlPath = $backupDir . DIRECTORY_SEPARATOR . $baseName . '.sql';
            $this->dumpDatabase($sqlPath);

            $archivePath = $this->createArchive($backupDir, $baseName, $sqlPath);
            $size = is_file($archivePath) ? filesize($archivePath) : 0;
            $checksum = is_file($archivePath) ? hash_file('sha256', $archivePath) : null;
            $durationMs = (int) round((microtime(true) - $started) * 1000);
            $relativePath = $this->relativeStoragePath($archivePath);

            $stmt = $pdo->prepare("UPDATE backup_runs
                SET status = 'ok', finished_at = NOW(), duration_ms = :duration_ms, file_path = :file_path,
                    file_size = :file_size, checksum_sha256 = :checksum_sha256, last_error = NULL
                WHERE id = :id");
            $stmt->execute([
                'duration_ms' => $durationMs,
                'file_path' => $relativePath,
                'file_size' => $size ?: null,
                'checksum_sha256' => $checksum,
                'id' => $runId,
            ]);

            @unlink($sqlPath);

            $webdavResult = (new WebDavService($this->auditService))->syncBackupRun($runId, $archivePath);
            if (($webdavResult['status'] ?? '') === 'failed') {
                Logger::error('Backup WebDAV sync failed', [
                    'backup_run_id' => $runId,
                    'message' => (string) ($webdavResult['error'] ?? 'unknown'),
                ]);
            }

            $this->auditService->record('backups.started', $this->currentUserId(), 'backup_run', $runId, [
                'backup_type' => $type,
                'status' => 'ok',
                'file_size' => $size,
                'webdav_status' => (string) ($webdavResult['status'] ?? 'skipped'),
            ]);
            $this->cleanupOldBackups();
            return $this->findRun($runId) ?? ['id' => $runId, 'status' => 'ok'];
        } catch (Throwable $exception) {
            $durationMs = (int) round((microtime(true) - $started) * 1000);
            $stmt = $pdo->prepare("UPDATE backup_runs
                SET status = 'failed', finished_at = NOW(), duration_ms = :duration_ms, last_error = :last_error
                WHERE id = :id");
            $stmt->execute([
                'duration_ms' => $durationMs,
                'last_error' => mb_substr($exception->getMessage(), 0, 60000),
                'id' => $runId,
            ]);
            Logger::error('Backup failed', ['backup_run_id' => $runId, 'message' => $exception->getMessage()]);
            $this->auditService->record('backups.started', $this->currentUserId(), 'backup_run', $runId, [
                'backup_type' => $type,
                'status' => 'failed',
            ]);
            throw $exception;
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    public function absolutePathForRun(array $run): string
    {
        $path = (string) ($run['file_path'] ?? '');
        if ($path === '') {
            throw new RuntimeException('Backup-Datei ist nicht verknüpft.');
        }
        $absolute = storage_path($path);
        $realStorage = realpath(storage_path());
        $realFile = realpath($absolute);
        if ($realStorage === false || $realFile === false || !str_starts_with($realFile, $realStorage)) {
            throw new RuntimeException('Backup-Pfad ist ungültig.');
        }
        if (!is_file($realFile)) {
            throw new RuntimeException('Backup-Datei wurde nicht gefunden.');
        }
        return $realFile;
    }

    public function cleanupOldBackups(): int
    {
        $days = (int) Config::get('app.backup.retention_days', 60);
        if ($days < 1) {
            $days = 60;
        }
        $stmt = Database::connection()->prepare("SELECT id, file_path FROM backup_runs
            WHERE status IN ('ok','partial') AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY) AND file_path IS NOT NULL");
        $stmt->bindValue('days', $days, PDO::PARAM_INT);
        $stmt->execute();
        $deleted = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $run) {
            try {
                $path = storage_path((string) $run['file_path']);
                if (is_file($path)) {
                    @unlink($path);
                }
                $update = Database::connection()->prepare("UPDATE backup_runs SET status = 'deleted', updated_at = NOW() WHERE id = :id");
                $update->execute(['id' => (int) $run['id']]);
                $deleted++;
            } catch (Throwable $exception) {
                Logger::error('Old backup cleanup failed', ['backup_run_id' => (int) $run['id'], 'message' => $exception->getMessage()]);
            }
        }
        return $deleted;
    }

    private function dumpDatabase(string $targetPath): void
    {
        $config = Config::get('database.connections.' . Config::get('database.default', 'mysql'), []);
        if (!is_array($config) || $config === []) {
            throw new RuntimeException('Datenbankkonfiguration fehlt.');
        }

        $mysqldump = trim((string) shell_exec('command -v mysqldump 2>/dev/null'));
        if ($mysqldump !== '') {
            $cmd = escapeshellcmd($mysqldump)
                . ' --single-transaction --skip-lock-tables --default-character-set=utf8mb4'
                . ' --host=' . escapeshellarg((string) ($config['host'] ?? '127.0.0.1'))
                . ' --port=' . escapeshellarg((string) ($config['port'] ?? '3306'))
                . ' --user=' . escapeshellarg((string) ($config['username'] ?? ''));
            $password = (string) ($config['password'] ?? '');
            if ($password !== '') {
                $cmd .= ' --password=' . escapeshellarg($password);
            }
            $cmd .= ' ' . escapeshellarg((string) ($config['database'] ?? '')) . ' > ' . escapeshellarg($targetPath) . ' 2>&1';
            exec($cmd, $output, $code);
            if ($code === 0 && is_file($targetPath) && filesize($targetPath) > 0) {
                return;
            }
            Logger::error('mysqldump failed, using PHP fallback', ['exit_code' => $code]);
        }

        $this->dumpDatabaseWithPhp($targetPath);
    }

    private function dumpDatabaseWithPhp(string $targetPath): void
    {
        $pdo = Database::connection();
        $handle = fopen($targetPath, 'wb');
        if ($handle === false) {
            throw new RuntimeException('SQL-Dumpdatei konnte nicht geschrieben werden.');
        }
        fwrite($handle, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
        $tables = $pdo->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'')->fetchAll(PDO::FETCH_NUM);
        foreach ($tables as $row) {
            $table = (string) $row[0];
            $quoted = $this->quoteIdentifier($table);
            $createStmt = $pdo->query('SHOW CREATE TABLE ' . $quoted)->fetch(PDO::FETCH_ASSOC);
            fwrite($handle, "DROP TABLE IF EXISTS {$quoted};\n");
            fwrite($handle, (string) ($createStmt['Create Table'] ?? '') . ";\n\n");

            $offset = 0;
            $limit = 500;
            while (true) {
                $rows = $pdo->query('SELECT * FROM ' . $quoted . ' LIMIT ' . $limit . ' OFFSET ' . $offset)->fetchAll(PDO::FETCH_ASSOC);
                if ($rows === []) {
                    break;
                }
                foreach ($rows as $data) {
                    $columns = array_map(fn (string $column): string => $this->quoteIdentifier($column), array_keys($data));
                    $values = array_map(fn (mixed $value): string => $value === null ? 'NULL' : $pdo->quote((string) $value), array_values($data));
                    fwrite($handle, 'INSERT INTO ' . $quoted . ' (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ");\n");
                }
                $offset += $limit;
            }
            fwrite($handle, "\n");
        }
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($handle);
        if (!is_file($targetPath) || filesize($targetPath) === 0) {
            throw new RuntimeException('PHP-SQL-Fallback hat keine Dumpdatei erzeugt.');
        }
    }

    private function createArchive(string $backupDir, string $baseName, string $sqlPath): string
    {
        $zipPath = $backupDir . DIRECTORY_SEPARATOR . $baseName . '.zip';
        if (class_exists(\ZipArchive::class)) {
            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('ZIP-Archiv konnte nicht erstellt werden.');
            }
            $zip->addFile($sqlPath, 'database/' . basename($sqlPath));
            foreach ($this->filesForBackup() as $file) {
                $zip->addFile($file['absolute'], $file['relative']);
            }
            $zip->close();
            return $zipPath;
        }

        if (!class_exists(\PharData::class)) {
            throw new RuntimeException('Weder ZipArchive noch PharData stehen für Backups zur Verfügung.');
        }
        $tarPath = $backupDir . DIRECTORY_SEPARATOR . $baseName . '.tar';
        if (is_file($tarPath)) {
            @unlink($tarPath);
        }
        $tar = new \PharData($tarPath);
        $tar->addFile($sqlPath, 'database/' . basename($sqlPath));
        foreach ($this->filesForBackup() as $file) {
            $tar->addFile($file['absolute'], $file['relative']);
        }
        return $tarPath;
    }

    private function filesForBackup(): array
    {
        $includeRoots = ['app', 'config', 'database', 'docs', 'public', 'routes', 'scripts', 'tests'];
        $files = [];
        foreach ($includeRoots as $root) {
            $this->collectFiles(base_path($root), $root, $files);
        }
        foreach (['README.md', 'CHANGELOG.md', 'VERSION'] as $file) {
            $absolute = base_path($file);
            if (is_file($absolute)) {
                $files[] = ['absolute' => $absolute, 'relative' => $file];
            }
        }
        foreach (self::INCLUDED_STORAGE_DIRS as $dir) {
            $this->collectFiles(storage_path($dir), 'storage/' . $dir, $files);
        }
        return $files;
    }

    private function collectFiles(string $dir, string $relativeRoot, array &$files): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }
            $absolute = $file->getPathname();
            $relative = $relativeRoot . '/' . ltrim(str_replace($dir, '', $absolute), DIRECTORY_SEPARATOR);
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
            if (str_contains($relative, 'storage/backups/') || str_contains($relative, 'storage/logs/') || str_contains($relative, 'storage/temp/')) {
                continue;
            }
            $files[] = ['absolute' => $absolute, 'relative' => $relative];
        }
    }

    private function relativeStoragePath(string $absolutePath): string
    {
        $base = rtrim(storage_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($absolutePath, $base)) {
            throw new RuntimeException('Backup-Datei liegt nicht im Storage.');
        }
        return str_replace(DIRECTORY_SEPARATOR, '/', substr($absolutePath, strlen($base)));
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function currentUserId(): ?int
    {
        $user = Auth::user();
        return $user === null ? null : (int) $user['user_id'];
    }
}
