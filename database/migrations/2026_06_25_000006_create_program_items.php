<?php

declare(strict_types=1);

return [
    'id' => '2026_06_25_000006_create_program_items',
    'up' => static function (PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS program_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            camp_year_id BIGINT UNSIGNED NOT NULL,
            program_date DATE NOT NULL,
            starts_at TIME NULL,
            ends_at TIME NULL,
            title VARCHAR(190) NOT NULL,
            category_key VARCHAR(80) NOT NULL DEFAULT 'info',
            location VARCHAR(190) NULL,
            responsible_label VARCHAR(190) NULL,
            description TEXT NULL,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            is_visible TINYINT(1) NOT NULL DEFAULT 1,
            created_by BIGINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            deleted_at DATETIME NULL,
            KEY idx_program_items_camp_date (camp_year_id, program_date),
            KEY idx_program_items_visible (is_visible),
            KEY idx_program_items_time (starts_at),
            KEY idx_program_items_category (category_key),
            KEY idx_program_items_deleted (deleted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS program_item_orders (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            program_item_id BIGINT UNSIGNED NOT NULL,
            order_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uq_program_item_order (program_item_id, order_id),
            KEY idx_program_item_orders_item (program_item_id),
            KEY idx_program_item_orders_order (order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $permissions = [
            'admin' => ['program.view', 'program.manage'],
            'lagerleitung' => ['program.view', 'program.manage'],
            'bereichsleitung' => ['program.view', 'program.manage'],
            'mitarbeiter' => ['program.view'],
            'lesen' => ['program.view'],
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
            'version' => '0.5.0',
            'notes' => 'Programm und Tagesablauf',
        ]);
    },
];
