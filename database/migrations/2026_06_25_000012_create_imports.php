<?php

declare(strict_types=1);

return [
    'id' => '2026_06_25_000012_create_imports',
    'up' => static function (PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS uploaded_files (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NULL,
            owner_type VARCHAR(80) NULL,
            owner_id BIGINT UNSIGNED NULL,
            category VARCHAR(80) NULL,
            original_name VARCHAR(255) NOT NULL,
            stored_name VARCHAR(255) NOT NULL,
            storage_path VARCHAR(500) NOT NULL,
            mime_type VARCHAR(190) NULL,
            file_ext VARCHAR(20) NULL,
            file_size BIGINT UNSIGNED NULL,
            checksum_sha256 CHAR(64) NULL,
            visibility ENUM('private','tenant','public_token') NOT NULL DEFAULT 'private',
            public_token_hash VARCHAR(255) NULL,
            uploaded_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            deleted_at DATETIME NULL,
            KEY idx_uploaded_files_owner (owner_type, owner_id),
            KEY idx_uploaded_files_category (category),
            KEY idx_uploaded_files_uploaded_by (uploaded_by),
            KEY idx_uploaded_files_deleted (deleted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS import_runs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            camp_year_id BIGINT UNSIGNED NULL,
            import_key VARCHAR(120) NOT NULL,
            original_file_id BIGINT UNSIGNED NULL,
            status ENUM('uploaded','preview','running','ok','failed','partial') NOT NULL DEFAULT 'uploaded',
            started_at DATETIME NULL,
            finished_at DATETIME NULL,
            total_rows INT UNSIGNED NULL,
            imported_rows INT UNSIGNED NULL,
            skipped_rows INT UNSIGNED NULL,
            error_rows INT UNSIGNED NULL,
            summary_json JSON NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            KEY idx_import_runs_camp (camp_year_id),
            KEY idx_import_runs_key (import_key),
            KEY idx_import_runs_status (status),
            KEY idx_import_runs_file (original_file_id),
            KEY idx_import_runs_created_by (created_by),
            KEY idx_import_runs_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS import_run_errors (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            import_run_id BIGINT UNSIGNED NOT NULL,
            source_row_number INT UNSIGNED NULL,
            field_name VARCHAR(120) NULL,
            error_text TEXT NOT NULL,
            raw_row_json JSON NULL,
            created_at DATETIME NOT NULL,
            KEY idx_import_run_errors_run (import_run_id),
            KEY idx_import_run_errors_row (source_row_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $permissions = [
            'admin' => ['imports.manage'],
            'lagerleitung' => ['imports.manage'],
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
            'version' => '0.11.0',
            'notes' => 'Kontrollierte Importe für XLSX, ODS und DOCX mit Upload, Vorschau und Protokoll',
        ]);
    },
];
