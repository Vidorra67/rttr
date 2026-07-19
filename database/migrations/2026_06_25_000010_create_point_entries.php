<?php

declare(strict_types=1);

return [
    'id' => '2026_06_25_000010_create_point_entries',
    'up' => static function (PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS point_categories (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            key_name VARCHAR(80) NOT NULL,
            label VARCHAR(190) NOT NULL,
            is_staff_selectable TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT UNSIGNED NOT NULL DEFAULT 100,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            UNIQUE KEY uq_point_categories_key (key_name),
            KEY idx_point_categories_active (is_active),
            KEY idx_point_categories_staff (is_staff_selectable),
            KEY idx_point_categories_sort (sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS point_entries (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            camp_year_id BIGINT UNSIGNED NOT NULL,
            person_id BIGINT UNSIGNED NOT NULL,
            order_id BIGINT UNSIGNED NULL,
            category_id BIGINT UNSIGNED NOT NULL,
            points INT NOT NULL,
            reason TEXT NOT NULL,
            source_type ENUM('ordnung_abzug','korrektur','import','system') NOT NULL DEFAULT 'ordnung_abzug',
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            voided_at DATETIME NULL,
            voided_by BIGINT UNSIGNED NULL,
            void_reason TEXT NULL,
            KEY idx_point_entries_camp (camp_year_id),
            KEY idx_point_entries_person (person_id),
            KEY idx_point_entries_order (order_id),
            KEY idx_point_entries_category (category_id),
            KEY idx_point_entries_created_by (created_by),
            KEY idx_point_entries_created_at (created_at),
            KEY idx_point_entries_voided (voided_at),
            KEY idx_point_entries_source (source_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $categories = [
            ['ordnung', 'Ordnung', 1, 10],
            ['spiel', 'Spiel', 0, 20],
            ['wettbewerb', 'Wettbewerb', 0, 30],
            ['pruefung', 'Prüfung', 0, 40],
            ['bonus', 'Bonus', 0, 50],
        ];
        $stmt = $pdo->prepare("INSERT IGNORE INTO point_categories
            (key_name, label, is_staff_selectable, is_active, sort_order, created_at, updated_at)
            VALUES (:key_name, :label, :is_staff_selectable, 1, :sort_order, NOW(), NOW())");
        foreach ($categories as $category) {
            [$key, $label, $staffSelectable, $sort] = $category;
            $stmt->execute([
                'key_name' => $key,
                'label' => $label,
                'is_staff_selectable' => $staffSelectable,
                'sort_order' => $sort,
            ]);
        }

        $permissions = [
            'admin' => ['points.order.create', 'points.manage'],
            'lagerleitung' => ['points.order.create', 'points.manage'],
            'bereichsleitung' => ['points.order.create'],
            'mitarbeiter' => ['points.order.create'],
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
            'version' => '0.9.0',
            'notes' => 'Ordnungspunkte und mobiler Punkteabzug',
        ]);
    },
];
