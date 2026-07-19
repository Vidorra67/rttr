<?php

declare(strict_types=1);

return [
    'id' => '2026_06_25_000005_create_camp_years_orders',
    'up' => static function (PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS camp_years (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(190) NOT NULL,
            location_name VARCHAR(190) NULL,
            starts_on DATE NOT NULL,
            ends_on DATE NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            KEY idx_camp_years_dates (starts_on, ends_on)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            camp_year_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(190) NOT NULL,
            short_name VARCHAR(80) NOT NULL,
            color_key VARCHAR(80) NULL,
            leader_person_id BIGINT UNSIGNED NULL,
            helper_person_id BIGINT UNSIGNED NULL,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            UNIQUE KEY uq_orders_camp_short_name (camp_year_id, short_name),
            KEY idx_orders_camp_year (camp_year_id),
            KEY idx_orders_active (is_active),
            KEY idx_orders_sort (sort_order),
            KEY idx_orders_leader (leader_person_id),
            KEY idx_orders_helper (helper_person_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS order_staff_assignments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            order_id BIGINT UNSIGNED NOT NULL,
            person_id BIGINT UNSIGNED NOT NULL,
            role_key VARCHAR(80) NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uq_order_staff_role (order_id, person_id, role_key),
            KEY idx_order_staff_order (order_id),
            KEY idx_order_staff_person (person_id),
            KEY idx_order_staff_role_key (role_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $permissions = [
            'admin' => ['camp_years.view', 'camp_years.manage', 'orders.view', 'orders.manage'],
            'lagerleitung' => ['camp_years.view', 'camp_years.manage', 'orders.view', 'orders.manage'],
            'bereichsleitung' => ['camp_years.view', 'orders.view'],
            'mitarbeiter' => ['camp_years.view', 'orders.view'],
            'lesen' => ['camp_years.view', 'orders.view'],
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
            'version' => '0.4.0',
            'notes' => 'Lagerjahr, Orden/Zelte und Übersicht',
        ]);
    },
];
