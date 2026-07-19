<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuditService;
use App\Services\BackupService;
use App\Services\ScheduledTaskService;
use App\Services\WebDavService;
use App\Support\Auth;
use App\Support\Config;
use App\Support\Csrf;
use App\Support\Database;
use App\Support\Flash;
use App\Support\Logger;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;
use Throwable;

final class SystemController
{
    public function __construct(
        private readonly BackupService $backupService = new BackupService(),
        private readonly ScheduledTaskService $scheduledTaskService = new ScheduledTaskService(),
        private readonly AuditService $auditService = new AuditService(),
        private readonly WebDavService $webDavService = new WebDavService()
    ) {
    }

    public function status(): string
    {
        if (!Auth::requirePermission('settings.manage')) {
            return '';
        }

        $checks = [
            'php_version' => PHP_VERSION,
            'app_environment' => Config::get('app.environment', 'unknown'),
            'storage_writable' => is_writable(storage_path()),
            'logs_writable' => is_writable(storage_path('logs')),
            'backups_writable' => is_writable(storage_path('backups')),
            'imports_writable' => is_writable(storage_path('imports')),
            'ziparchive_available' => class_exists(\ZipArchive::class),
            'phardata_available' => class_exists(\PharData::class),
            'version' => trim((string) @file_get_contents(base_path('VERSION'))),
            'database' => 'not_checked',
        ];

        try {
            Database::connection()->query('SELECT 1');
            $checks['database'] = 'ok';
        } catch (Throwable $exception) {
            $checks['database'] = 'not_connected';
            Logger::error('Database status check failed', ['message' => $exception->getMessage()]);
        }

        return View::render('system/status', [
            'title' => 'Systemstatus',
            'checks' => $checks,
        ]);
    }

    public function backups(): string
    {
        if (!Auth::requirePermission('backups.manage')) {
            return '';
        }

        try {
            $runs = $this->backupService->runs();
        } catch (Throwable $exception) {
            Logger::error('Backup list failed', ['message' => $exception->getMessage()]);
            Flash::add('error', 'Backupliste konnte nicht geladen werden.');
            $runs = [];
        }

        return View::render('system/backups', [
            'title' => 'Backups',
            'runs' => $runs,
        ]);
    }

    public function startBackup(): string
    {
        if (!Auth::requirePermission('backups.manage')) {
            return '';
        }
        if (!Csrf::verify(Request::post('_csrf'))) {
            Response::html(View::render('errors/403', ['permission' => 'csrf']), 403);
            return '';
        }

        try {
            $run = $this->backupService->create('manual');
            Flash::add('success', 'Backup wurde erstellt. Status: ' . (string) ($run['status'] ?? 'ok'));
        } catch (Throwable $exception) {
            Logger::error('Manual backup failed', ['message' => $exception->getMessage()]);
            Flash::add('error', $exception->getMessage());
        }

        Response::redirect('/system/backups');
        return '';
    }

    public function downloadBackup(): string
    {
        if (!Auth::requirePermission('backups.download')) {
            return '';
        }

        $id = Request::intFromGet('id');
        if ($id === null) {
            Response::html(View::render('errors/404', ['path' => '/system/backups/download']), 404);
            return '';
        }

        try {
            $run = $this->backupService->findRun($id);
            if ($run === null) {
                Response::html(View::render('errors/404', ['path' => '/system/backups/download']), 404);
                return '';
            }
            $file = $this->backupService->absolutePathForRun($run);
            $this->auditService->record('backups.downloaded', Auth::user()['user_id'] ?? null, 'backup_run', $id, [
                'backup_type' => (string) ($run['backup_type'] ?? ''),
                'file_size' => (int) ($run['file_size'] ?? 0),
            ]);

            $downloadName = basename($file);
            header('Content-Type: application/octet-stream');
            header('Content-Length: ' . filesize($file));
            header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
            header('X-Content-Type-Options: nosniff');
            readfile($file);
            exit;
        } catch (Throwable $exception) {
            Logger::error('Backup download failed', ['backup_run_id' => $id, 'message' => $exception->getMessage()]);
            Flash::add('error', $exception->getMessage());
            Response::redirect('/system/backups');
            return '';
        }
    }

    public function tasks(): string
    {
        if (!Auth::requirePermission('cron.manage')) {
            return '';
        }

        try {
            $tasks = $this->scheduledTaskService->tasks();
            $runs = $this->scheduledTaskService->runs();
        } catch (Throwable $exception) {
            Logger::error('Scheduled tasks list failed', ['message' => $exception->getMessage()]);
            Flash::add('error', 'Geplante Aufgaben konnten nicht geladen werden.');
            $tasks = [];
            $runs = [];
        }

        return View::render('system/tasks', [
            'title' => 'Geplante Aufgaben',
            'tasks' => $tasks,
            'runs' => $runs,
            'baseUrl' => rtrim((string) Config::get('app.base_url', ''), '/'),
            'lastCronUrl' => $_SESSION['_last_cron_url'] ?? null,
        ]);
    }

    public function runTask(): string
    {
        if (!Auth::requirePermission('cron.manage')) {
            return '';
        }
        if (!Csrf::verify(Request::post('_csrf'))) {
            Response::html(View::render('errors/403', ['permission' => 'csrf']), 403);
            return '';
        }
        $id = Request::intFromPost('id');
        if ($id !== null) {
            try {
                $result = $this->scheduledTaskService->runById($id, 'admin');
                Flash::add($result['status'] === 'ok' ? 'success' : 'error', (string) ($result['output'] ?? $result['error'] ?? 'Aufgabe wurde ausgeführt.'));
            } catch (Throwable $exception) {
                Logger::error('Scheduled task admin run failed', ['task_id' => $id, 'message' => $exception->getMessage()]);
                Flash::add('error', $exception->getMessage());
            }
        }
        Response::redirect('/system/tasks');
        return '';
    }

    public function regenerateTaskToken(): string
    {
        if (!Auth::requirePermission('cron.manage')) {
            return '';
        }
        if (!Csrf::verify(Request::post('_csrf'))) {
            Response::html(View::render('errors/403', ['permission' => 'csrf']), 403);
            return '';
        }
        $id = Request::intFromPost('id');
        if ($id !== null) {
            try {
                $result = $this->scheduledTaskService->regenerateToken($id);
                $taskKey = (string) ($result['task']['task_key'] ?? '');
                $baseUrl = rtrim((string) Config::get('app.base_url', ''), '/');
                $url = ($baseUrl !== '' ? $baseUrl : '') . '/cron/run?task=' . rawurlencode($taskKey) . '&token=' . rawurlencode((string) $result['token']);
                $_SESSION['_last_cron_url'] = $url;
                Flash::add('success', 'Cron-Token wurde neu erzeugt. Die vollständige URL wird einmalig angezeigt.');
            } catch (Throwable $exception) {
                Logger::error('Scheduled task token regeneration failed', ['task_id' => $id, 'message' => $exception->getMessage()]);
                Flash::add('error', $exception->getMessage());
            }
        }
        Response::redirect('/system/tasks');
        return '';
    }

    public function toggleTask(): string
    {
        if (!Auth::requirePermission('cron.manage')) {
            return '';
        }
        if (!Csrf::verify(Request::post('_csrf'))) {
            Response::html(View::render('errors/403', ['permission' => 'csrf']), 403);
            return '';
        }
        $id = Request::intFromPost('id');
        if ($id !== null) {
            try {
                $this->scheduledTaskService->setActive($id, (string) Request::post('active', '0') === '1');
                Flash::add('success', 'Aufgabenstatus wurde gespeichert.');
            } catch (Throwable $exception) {
                Logger::error('Scheduled task toggle failed', ['task_id' => $id, 'message' => $exception->getMessage()]);
                Flash::add('error', $exception->getMessage());
            }
        }
        Response::redirect('/system/tasks');
        return '';
    }


    public function webdav(): string
    {
        if (!Auth::requirePermission('webdav.manage')) {
            return '';
        }

        try {
            $settings = $this->webDavService->settings();
            $runs = $this->webDavService->recentRuns();
        } catch (Throwable $exception) {
            Logger::error('WebDAV settings load failed', ['message' => $exception->getMessage()]);
            Flash::add('error', 'WebDAV-Einstellungen konnten nicht geladen werden.');
            $settings = [
                'enabled' => false,
                'base_url' => '',
                'username' => '',
                'password_set' => false,
                'remote_base_path' => 'ritterlager/backups',
                'timeout_seconds' => 45,
            ];
            $runs = [];
        }

        return View::render('system/webdav', [
            'title' => 'WebDAV',
            'settings' => $settings,
            'runs' => $runs,
        ]);
    }

    public function saveWebdav(): string
    {
        if (!Auth::requirePermission('webdav.manage')) {
            return '';
        }
        if (!Csrf::verify(Request::post('_csrf'))) {
            Response::html(View::render('errors/403', ['permission' => 'csrf']), 403);
            return '';
        }

        try {
            $this->webDavService->save($_POST);
            Flash::add('success', 'WebDAV-Einstellungen wurden gespeichert.');
        } catch (Throwable $exception) {
            Logger::error('WebDAV settings save failed', ['message' => $exception->getMessage()]);
            Flash::add('error', $exception->getMessage());
        }

        Response::redirect('/system/webdav');
        return '';
    }

    public function testWebdav(): string
    {
        if (!Auth::requirePermission('webdav.manage')) {
            return '';
        }
        if (!Csrf::verify(Request::post('_csrf'))) {
            Response::html(View::render('errors/403', ['permission' => 'csrf']), 403);
            return '';
        }

        try {
            $result = $this->webDavService->testConnection();
            Flash::add('success', 'WebDAV-Test erfolgreich. HTTP-Status: ' . (string) ($result['http_status'] ?? 'ok'));
        } catch (Throwable $exception) {
            Logger::error('WebDAV test failed', ['message' => $exception->getMessage()]);
            Flash::add('error', $exception->getMessage());
        }

        Response::redirect('/system/webdav');
        return '';
    }

    public function syncLatestWebdav(): string
    {
        if (!Auth::requirePermission('webdav.manage')) {
            return '';
        }
        if (!Csrf::verify(Request::post('_csrf'))) {
            Response::html(View::render('errors/403', ['permission' => 'csrf']), 403);
            return '';
        }

        try {
            $result = $this->webDavService->syncLatestBackup();
            if (($result['status'] ?? '') === 'ok') {
                Flash::add('success', 'Letztes Backup wurde zu WebDAV übertragen.');
            } elseif (($result['status'] ?? '') === 'skipped') {
                Flash::add('error', (string) ($result['message'] ?? 'WebDAV ist nicht konfiguriert.'));
            } else {
                Flash::add('error', (string) ($result['error'] ?? 'WebDAV-Sync fehlgeschlagen.'));
            }
        } catch (Throwable $exception) {
            Logger::error('WebDAV latest backup sync failed', ['message' => $exception->getMessage()]);
            Flash::add('error', $exception->getMessage());
        }

        Response::redirect('/system/webdav');
        return '';
    }

    public function logs(): string
    {
        if (!Auth::requirePermission('logs.view')) {
            return '';
        }

        $files = [];
        foreach (glob(storage_path('logs/*.log')) ?: [] as $file) {
            if (!is_file($file)) {
                continue;
            }
            $files[] = [
                'name' => basename($file),
                'size' => filesize($file),
                'modified_at' => date('Y-m-d H:i:s', filemtime($file) ?: time()),
            ];
        }
        usort($files, static fn (array $a, array $b): int => strcmp((string) $b['name'], (string) $a['name']));

        $selected = (string) Request::get('file', ($files[0]['name'] ?? ''));
        $lines = [];
        if ($selected !== '' && preg_match('/^app-\d{4}-\d{2}-\d{2}\.log$/', $selected)) {
            $path = storage_path('logs/' . $selected);
            if (is_file($path)) {
                $all = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
                $lines = array_slice($all, -120);
            }
        }

        return View::render('system/logs', [
            'title' => 'Logs',
            'files' => $files,
            'selected' => $selected,
            'lines' => $lines,
        ]);
    }

    public function cronRun(): void
    {
        $task = (string) Request::get('task', '');
        $token = (string) Request::get('token', '');
        try {
            $result = $this->scheduledTaskService->runHttp($task, $token);
            $status = (string) ($result['status'] ?? 'error');
            $code = $status === 'ok' || $status === 'skipped' ? 200 : 500;
            Response::json($result, $code);
        } catch (Throwable $exception) {
            Logger::error('HTTP cron failed', ['task' => $task, 'message' => $exception->getMessage()]);
            Response::json(['status' => 'error', 'error' => $exception->getMessage()], 403);
        }
    }

    public function health(): void
    {
        Response::json([
            'status' => 'ok',
            'version' => trim((string) @file_get_contents(base_path('VERSION'))),
        ]);
    }
}
