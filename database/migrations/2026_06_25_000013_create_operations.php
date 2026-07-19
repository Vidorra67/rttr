<?php

declare(strict_types=1);

return [
    'id' => '2026_06_25_000013_create_operations',
    'up' => static function (PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS backup_runs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NULL,
            backup_type ENUM('daily','weekly','monthly','quarterly','manual') NOT NULL,
            status ENUM('running','ok','failed','partial','deleted') NOT NULL DEFAULT 'running',
            started_at DATETIME NOT NULL,
            finished_at DATETIME NULL,
            duration_ms INT UNSIGNED NULL,
            file_path VARCHAR(500) NULL,
            file_size BIGINT UNSIGNED NULL,
            checksum_sha256 CHAR(64) NULL,
            includes_database TINYINT(1) NOT NULL DEFAULT 0,
            includes_files TINYINT(1) NOT NULL DEFAULT 0,
            includes_config TINYINT(1) NOT NULL DEFAULT 0,
            includes_uploads TINYINT(1) NOT NULL DEFAULT 0,
            includes_documents TINYINT(1) NOT NULL DEFAULT 0,
            webdav_status ENUM('not_configured','pending','ok','failed') NULL,
            last_error MEDIUMTEXT NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            KEY idx_backup_runs_type (backup_type),
            KEY idx_backup_runs_status (status),
            KEY idx_backup_runs_started (started_at),
            KEY idx_backup_runs_created_by (created_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS scheduled_tasks (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            task_key VARCHAR(120) NOT NULL,
            label VARCHAR(190) NOT NULL,
            description TEXT NULL,
            recommended_interval VARCHAR(80) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            token_hash VARCHAR(255) NULL,
            locked_until DATETIME NULL,
            last_run_at DATETIME NULL,
            last_status ENUM('ok','skipped','error') NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            UNIQUE KEY uq_scheduled_tasks_key (task_key),
            KEY idx_scheduled_tasks_active (is_active),
            KEY idx_scheduled_tasks_locked (locked_until)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS scheduled_task_runs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            task_id BIGINT UNSIGNED NOT NULL,
            status ENUM('ok','skipped','error') NOT NULL,
            started_at DATETIME NOT NULL,
            finished_at DATETIME NULL,
            duration_ms INT UNSIGNED NULL,
            exit_code INT NULL,
            processed_count INT UNSIGNED NULL,
            output_text MEDIUMTEXT NULL,
            error_text MEDIUMTEXT NULL,
            triggered_by ENUM('cli','http','admin') NOT NULL DEFAULT 'cli',
            created_at DATETIME NOT NULL,
            KEY idx_scheduled_task_runs_task (task_id),
            KEY idx_scheduled_task_runs_status (status),
            KEY idx_scheduled_task_runs_started (started_at),
            KEY idx_scheduled_task_runs_triggered (triggered_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS webdav_sync_runs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NULL,
            source_type VARCHAR(80) NOT NULL,
            source_id BIGINT UNSIGNED NULL,
            local_path VARCHAR(500) NOT NULL,
            remote_path VARCHAR(500) NOT NULL,
            status ENUM('pending','running','ok','failed','skipped') NOT NULL DEFAULT 'pending',
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            last_error MEDIUMTEXT NULL,
            started_at DATETIME NULL,
            finished_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            KEY idx_webdav_sync_source (source_type, source_id),
            KEY idx_webdav_sync_status (status),
            KEY idx_webdav_sync_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $taskStmt = $pdo->prepare("INSERT IGNORE INTO scheduled_tasks
            (task_key, label, description, recommended_interval, is_active, created_at, updated_at)
            VALUES (:task_key, :label, :description, :recommended_interval, 1, NOW(), NOW())");
        $tasks = [
            ['backup_daily', 'Tägliches Backup', 'Erzeugt ein tägliches Backup aus Datenbank und Dateien.', 'täglich'],
            ['backup_weekly', 'Wöchentliches Backup', 'Erzeugt ein wöchentliches Backup aus Datenbank und Dateien.', 'wöchentlich'],
            ['cleanup_logs', 'Logs bereinigen', 'Entfernt alte technische Logdateien nach Aufbewahrungszeit.', 'täglich'],
        ];
        foreach ($tasks as $task) {
            $taskStmt->execute([
                'task_key' => $task[0],
                'label' => $task[1],
                'description' => $task[2],
                'recommended_interval' => $task[3],
            ]);
        }

        $permissions = [
            'admin' => ['backups.manage', 'backups.download', 'cron.manage', 'logs.view'],
            'lagerleitung' => ['backups.manage', 'backups.download', 'cron.manage', 'logs.view'],
        ];
        $roleIdStmt = $pdo->prepare('SELECT id FROM roles WHERE key_name = :key_name LIMIT 1');
        $permissionStmt = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_key)
            VALUES (:role_id, :permission_key)");
        foreach ($permissions as $roleKey => $permissionList) {
            $roleIdStmt->execute(['key_name' => $roleKey]);
            $roleId = $roleIdStmt->fetchColumn();
            if ($roleId === false) {
                continue;
            }
            foreach ($permissionList as $permission) {
                $permissionStmt->execute(['role_id' => (int) $roleId, 'permission_key' => $permission]);
            }
        }

        $stmt = $pdo->prepare("INSERT IGNORE INTO app_versions (version, applied_at, notes, created_at)
            VALUES (:version, NOW(), :notes, NOW())");
        $stmt->execute([
            'version' => '0.12.0',
            'notes' => 'Betrieb: Backups, geplante Aufgaben, HTTP-Cron, Logs und Restore-Dokumentation',
        ]);
    },
];
