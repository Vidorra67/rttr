<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Auth;
use App\Support\Database;
use App\Support\Logger;
use PDO;
use RuntimeException;
use Throwable;

final class WebDavService
{
    private const SCOPE = 'webdav';

    public function __construct(private readonly AuditService $auditService = new AuditService())
    {
    }

    public function settings(): array
    {
        $values = $this->loadSettings();
        return [
            'enabled' => (($values['enabled']['value_text'] ?? '0') === '1'),
            'base_url' => (string) ($values['base_url']['value_text'] ?? ''),
            'username' => (string) ($values['username']['value_text'] ?? ''),
            'password' => (string) ($values['password']['value_text'] ?? ''),
            'password_set' => (string) ($values['password']['value_text'] ?? '') !== '',
            'remote_base_path' => (string) ($values['remote_base_path']['value_text'] ?? 'ritterlager/backups'),
            'timeout_seconds' => (int) ($values['timeout_seconds']['value_text'] ?? 45),
        ];
    }

    public function isConfigured(): bool
    {
        $settings = $this->settings();
        return $settings['enabled'] === true
            && $settings['base_url'] !== ''
            && $settings['username'] !== ''
            && $settings['password'] !== '';
    }

    public function save(array $data): void
    {
        $enabled = !empty($data['enabled']) ? '1' : '0';
        $baseUrl = rtrim(trim((string) ($data['base_url'] ?? '')), '/');
        $username = trim((string) ($data['username'] ?? ''));
        $remoteBasePath = trim(str_replace('\\', '/', (string) ($data['remote_base_path'] ?? 'ritterlager/backups')), "/ \t\n\r\0\x0B");
        $timeout = (int) ($data['timeout_seconds'] ?? 45);
        if ($timeout < 5) {
            $timeout = 5;
        }
        if ($timeout > 300) {
            $timeout = 300;
        }

        if ($enabled === '1') {
            if ($baseUrl === '' || !filter_var($baseUrl, FILTER_VALIDATE_URL)) {
                throw new RuntimeException('Bitte eine gültige WebDAV-URL eintragen.');
            }
            if (!str_starts_with($baseUrl, 'https://')) {
                throw new RuntimeException('Bitte eine HTTPS-WebDAV-URL verwenden.');
            }
            if ($username === '') {
                throw new RuntimeException('Bitte einen WebDAV-Benutzernamen eintragen.');
            }
        }

        $this->upsert('enabled', $enabled, false);
        $this->upsert('base_url', $baseUrl, false);
        $this->upsert('username', $username, false);
        $this->upsert('remote_base_path', $remoteBasePath !== '' ? $remoteBasePath : 'ritterlager/backups', false);
        $this->upsert('timeout_seconds', (string) $timeout, false);

        $password = (string) ($data['password'] ?? '');
        if ($password !== '') {
            $this->upsert('password', $password, true);
        } elseif (!$this->settings()['password_set']) {
            $this->upsert('password', '', true);
        }

        $this->auditService->record('webdav.settings_updated', $this->currentUserId(), null, null, [
            'enabled' => $enabled === '1',
            'base_url_changed' => true,
            'username_changed' => true,
            'password_changed' => $password !== '',
            'remote_base_path' => $remoteBasePath,
        ]);
    }

    public function testConnection(): array
    {
        $settings = $this->settings();
        $this->assertConfigured($settings);
        $this->assertCurlAvailable();

        $baseUrl = $this->baseUrl($settings);
        $response = $this->request('PROPFIND', $baseUrl, $settings, null, null, ['Depth: 0']);
        $ok = in_array($response['status'], [200, 207, 301, 302], true);
        $this->auditService->record('webdav.tested', $this->currentUserId(), null, null, [
            'status_code' => $response['status'],
            'ok' => $ok,
        ]);
        if (!$ok) {
            throw new RuntimeException('WebDAV-Test fehlgeschlagen. HTTP-Status: ' . $response['status']);
        }
        return ['status' => 'ok', 'http_status' => $response['status']];
    }

    public function recentRuns(int $limit = 50): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM webdav_sync_runs ORDER BY created_at DESC, id DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function syncBackupRun(int $backupRunId, ?string $absoluteFilePath = null): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM backup_runs WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $backupRunId]);
        $run = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($run)) {
            throw new RuntimeException('Backup-Lauf wurde nicht gefunden.');
        }
        if (($run['status'] ?? '') !== 'ok' && ($run['status'] ?? '') !== 'partial') {
            throw new RuntimeException('Nur erfolgreiche Backups können zu WebDAV synchronisiert werden.');
        }

        if ($absoluteFilePath === null) {
            $path = (string) ($run['file_path'] ?? '');
            if ($path === '') {
                throw new RuntimeException('Backup-Datei ist nicht verknüpft.');
            }
            $absoluteFilePath = storage_path($path);
        }
        if (!is_file($absoluteFilePath)) {
            throw new RuntimeException('Backup-Datei wurde nicht gefunden.');
        }

        $relativePath = (string) ($run['file_path'] ?? 'backups/' . basename($absoluteFilePath));
        return $this->syncFile('backup', $backupRunId, $absoluteFilePath, $relativePath);
    }

    public function syncLatestBackup(): array
    {
        $stmt = Database::connection()->query("SELECT id FROM backup_runs WHERE status IN ('ok','partial') AND file_path IS NOT NULL ORDER BY started_at DESC, id DESC LIMIT 1");
        $id = $stmt->fetchColumn();
        if ($id === false) {
            throw new RuntimeException('Es wurde kein synchronisierbares Backup gefunden.');
        }
        return $this->syncBackupRun((int) $id);
    }

    public function syncFile(string $sourceType, ?int $sourceId, string $absoluteFilePath, string $relativePath): array
    {
        $settings = $this->settings();
        if (!$this->isConfigured()) {
            $this->markBackupWebdavStatus($sourceType, $sourceId, 'not_configured', null);
            return ['status' => 'skipped', 'message' => 'WebDAV ist nicht konfiguriert.'];
        }
        $this->assertCurlAvailable();

        $remotePath = $this->remotePath($settings, $relativePath);
        $pdo = Database::connection();
        $insert = $pdo->prepare("INSERT INTO webdav_sync_runs
            (source_type, source_id, local_path, remote_path, status, attempts, started_at, created_at)
            VALUES (:source_type, :source_id, :local_path, :remote_path, 'running', 1, NOW(), NOW())");
        $insert->execute([
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'local_path' => $this->relativeStoragePathIfPossible($absoluteFilePath),
            'remote_path' => $remotePath,
        ]);
        $syncRunId = (int) $pdo->lastInsertId();
        $this->markBackupWebdavStatus($sourceType, $sourceId, 'pending', null);

        try {
            $this->ensureRemoteDirectories($settings, dirname($remotePath));
            $url = $this->urlForRemotePath($settings, $remotePath);
            $handle = fopen($absoluteFilePath, 'rb');
            if ($handle === false) {
                throw new RuntimeException('Lokale Datei konnte für WebDAV nicht geöffnet werden.');
            }
            try {
                $response = $this->request('PUT', $url, $settings, $handle, filesize($absoluteFilePath) ?: null);
            } finally {
                fclose($handle);
            }
            if (!in_array($response['status'], [200, 201, 204], true)) {
                throw new RuntimeException('WebDAV-Upload fehlgeschlagen. HTTP-Status: ' . $response['status']);
            }

            $update = $pdo->prepare("UPDATE webdav_sync_runs SET status = 'ok', finished_at = NOW(), last_error = NULL WHERE id = :id");
            $update->execute(['id' => $syncRunId]);
            $this->markBackupWebdavStatus($sourceType, $sourceId, 'ok', null);
            $this->auditService->record('webdav.synced', $this->currentUserId(), $sourceType, $sourceId, [
                'remote_path' => $remotePath,
                'status' => 'ok',
            ]);
            return ['status' => 'ok', 'remote_path' => $remotePath, 'http_status' => $response['status']];
        } catch (Throwable $exception) {
            $update = $pdo->prepare("UPDATE webdav_sync_runs SET status = 'failed', finished_at = NOW(), last_error = :last_error WHERE id = :id");
            $update->execute(['id' => $syncRunId, 'last_error' => mb_substr($exception->getMessage(), 0, 60000)]);
            $this->markBackupWebdavStatus($sourceType, $sourceId, 'failed', $exception->getMessage());
            Logger::error('WebDAV sync failed', ['source_type' => $sourceType, 'source_id' => $sourceId, 'message' => $exception->getMessage()]);
            return ['status' => 'failed', 'remote_path' => $remotePath, 'error' => $exception->getMessage()];
        }
    }

    private function ensureRemoteDirectories(array $settings, string $remoteDir): void
    {
        $remoteDir = trim(str_replace('\\', '/', $remoteDir), '/');
        if ($remoteDir === '' || $remoteDir === '.') {
            return;
        }
        $segments = explode('/', $remoteDir);
        $path = '';
        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }
            $path = $path === '' ? $segment : $path . '/' . $segment;
            $response = $this->request('MKCOL', $this->urlForRemotePath($settings, $path), $settings);
            if (!in_array($response['status'], [201, 405, 200], true)) {
                Logger::error('WebDAV MKCOL failed', ['remote_path' => $path, 'status' => $response['status']]);
            }
        }
    }

    private function request(string $method, string $url, array $settings, mixed $fileHandle = null, ?int $fileSize = null, array $headers = []): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('cURL konnte nicht initialisiert werden.');
        }
        $timeout = max(5, (int) ($settings['timeout_seconds'] ?? 45));
        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(15, $timeout),
            CURLOPT_USERPWD => (string) $settings['username'] . ':' . (string) $settings['password'],
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => $headers,
        ];
        if ($fileHandle !== null) {
            $options[CURLOPT_UPLOAD] = true;
            $options[CURLOPT_INFILE] = $fileHandle;
            if ($fileSize !== null) {
                $options[CURLOPT_INFILESIZE] = $fileSize;
            }
        }
        curl_setopt_array($ch, $options);
        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('WebDAV-Anfrage fehlgeschlagen: ' . $error);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['status' => $status, 'body' => (string) $body];
    }

    private function remotePath(array $settings, string $relativePath): string
    {
        $base = trim(str_replace('\\', '/', (string) ($settings['remote_base_path'] ?? 'ritterlager/backups')), '/');
        $relative = trim(str_replace('\\', '/', $relativePath), '/');
        return trim(($base !== '' ? $base . '/' : '') . $relative, '/');
    }

    private function urlForRemotePath(array $settings, string $remotePath): string
    {
        $base = $this->baseUrl($settings);
        $remotePath = trim(str_replace('\\', '/', $remotePath), '/');
        if ($remotePath === '') {
            return $base;
        }
        $encoded = implode('/', array_map('rawurlencode', array_filter(explode('/', $remotePath), static fn (string $part): bool => $part !== '')));
        return $base . '/' . $encoded;
    }

    private function baseUrl(array $settings): string
    {
        return rtrim((string) ($settings['base_url'] ?? ''), '/');
    }

    private function assertConfigured(array $settings): void
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('WebDAV ist noch nicht vollständig konfiguriert.');
        }
    }

    private function assertCurlAvailable(): void
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Die PHP-Erweiterung cURL ist für WebDAV erforderlich.');
        }
    }

    private function loadSettings(): array
    {
        $stmt = Database::connection()->prepare('SELECT key_name, value_text, is_secret FROM settings WHERE scope = :scope');
        $stmt->execute(['scope' => self::SCOPE]);
        $values = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $values[(string) $row['key_name']] = $row;
        }
        return $values;
    }

    private function upsert(string $key, string $value, bool $secret): void
    {
        $stmt = Database::connection()->prepare("INSERT INTO settings (scope, key_name, value_text, is_secret, created_at, updated_at)
            VALUES (:scope, :key_name, :value_text, :is_secret, NOW(), NOW())
            ON DUPLICATE KEY UPDATE value_text = VALUES(value_text), is_secret = VALUES(is_secret), updated_at = NOW()");
        $stmt->execute([
            'scope' => self::SCOPE,
            'key_name' => $key,
            'value_text' => $value,
            'is_secret' => $secret ? 1 : 0,
        ]);
    }

    private function markBackupWebdavStatus(string $sourceType, ?int $sourceId, string $status, ?string $error): void
    {
        if ($sourceType !== 'backup' || $sourceId === null) {
            return;
        }
        $allowed = ['not_configured', 'pending', 'ok', 'failed'];
        if (!in_array($status, $allowed, true)) {
            $status = 'failed';
        }
        $sql = "UPDATE backup_runs SET webdav_status = :status" . ($error !== null ? ', last_error = :last_error' : '') . " WHERE id = :id";
        $stmt = Database::connection()->prepare($sql);
        $params = ['status' => $status, 'id' => $sourceId];
        if ($error !== null) {
            $params['last_error'] = mb_substr($error, 0, 60000);
        }
        $stmt->execute($params);
    }

    private function relativeStoragePathIfPossible(string $absolutePath): string
    {
        $storage = rtrim(storage_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (str_starts_with($absolutePath, $storage)) {
            return str_replace(DIRECTORY_SEPARATOR, '/', substr($absolutePath, strlen($storage)));
        }
        return $absolutePath;
    }

    private function currentUserId(): ?int
    {
        $user = Auth::user();
        return $user === null ? null : (int) $user['user_id'];
    }
}
