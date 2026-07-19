<?php

declare(strict_types=1);

return [
    'id' => '2026_06_25_000011_create_rank_learning_exam_results',
    'up' => static function (PDO $pdo): void {
        $columnExists = static function (PDO $pdo, string $table, string $column): bool {
            $stmt = $pdo->prepare("SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table_name
                  AND COLUMN_NAME = :column_name");
            $stmt->execute(['table_name' => $table, 'column_name' => $column]);
            return (int) $stmt->fetchColumn() > 0;
        };

        $pdo->exec("CREATE TABLE IF NOT EXISTS rank_levels (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            camp_year_id BIGINT UNSIGNED NOT NULL,
            key_name VARCHAR(80) NOT NULL,
            label VARCHAR(190) NOT NULL,
            sort_order INT UNSIGNED NOT NULL DEFAULT 100,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            UNIQUE KEY uq_rank_levels_camp_key (camp_year_id, key_name),
            KEY idx_rank_levels_camp (camp_year_id),
            KEY idx_rank_levels_sort (sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS learning_units (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            camp_year_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(190) NOT NULL,
            category_key VARCHAR(80) NOT NULL DEFAULT 'lernen',
            responsible_label VARCHAR(190) NULL,
            sort_order INT UNSIGNED NOT NULL DEFAULT 100,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            deleted_at DATETIME NULL,
            KEY idx_learning_units_camp (camp_year_id),
            KEY idx_learning_units_category (category_key),
            KEY idx_learning_units_sort (sort_order),
            KEY idx_learning_units_deleted (deleted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS exam_results (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            camp_year_id BIGINT UNSIGNED NOT NULL,
            person_id BIGINT UNSIGNED NOT NULL,
            learning_unit_id BIGINT UNSIGNED NOT NULL,
            result_status ENUM('offen','bestanden','nicht_bestanden','teilgenommen','befreit') NOT NULL DEFAULT 'offen',
            points DECIMAL(8,2) NULL,
            note TEXT NULL,
            assessed_by BIGINT UNSIGNED NULL,
            assessed_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            UNIQUE KEY uq_exam_results_person_unit (camp_year_id, person_id, learning_unit_id),
            KEY idx_exam_results_camp (camp_year_id),
            KEY idx_exam_results_person (person_id),
            KEY idx_exam_results_unit (learning_unit_id),
            KEY idx_exam_results_status (result_status),
            KEY idx_exam_results_assessed_by (assessed_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        if (!$columnExists($pdo, 'camp_person_statuses', 'rank_level_id')) {
            $pdo->exec("ALTER TABLE camp_person_statuses ADD COLUMN rank_level_id BIGINT UNSIGNED NULL AFTER rank_label");
            $pdo->exec("ALTER TABLE camp_person_statuses ADD KEY idx_camp_person_status_rank_level (rank_level_id)");
        }

        $permissions = [
            'admin' => ['exams.view', 'exams.manage'],
            'lagerleitung' => ['exams.view', 'exams.manage'],
            'bereichsleitung' => ['exams.view'],
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
            'version' => '0.10.0',
            'notes' => 'Auswertungen, Rangordnung, Lerneinheiten und Prüfungsergebnisse',
        ]);
    },
];
