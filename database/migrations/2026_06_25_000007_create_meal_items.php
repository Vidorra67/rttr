<?php

declare(strict_types=1);

return [
    'id' => '2026_06_25_000007_create_meal_items',
    'up' => static function (PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS meal_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            camp_year_id BIGINT UNSIGNED NOT NULL,
            meal_date DATE NOT NULL,
            meal_type ENUM('fruehstueck','mittagessen','abendessen') NOT NULL,
            meal_time TIME NULL,
            title VARCHAR(190) NOT NULL,
            portions_total INT UNSIGNED NOT NULL DEFAULT 0,
            portions_vegetarian INT UNSIGNED NOT NULL DEFAULT 0,
            allergy_notes TEXT NULL,
            kitchen_team_label VARCHAR(190) NULL,
            description TEXT NULL,
            created_by BIGINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            deleted_at DATETIME NULL,
            KEY idx_meal_items_camp_date (camp_year_id, meal_date),
            KEY idx_meal_items_type (meal_type),
            KEY idx_meal_items_deleted (deleted_at),
            KEY idx_meal_items_time (meal_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS meal_ingredients (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            meal_item_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(190) NOT NULL,
            quantity DECIMAL(12,3) NULL,
            unit VARCHAR(40) NULL,
            note VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            KEY idx_meal_ingredients_item (meal_item_id),
            KEY idx_meal_ingredients_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $permissions = [
            'admin' => ['meals.view', 'meals.manage'],
            'lagerleitung' => ['meals.view', 'meals.manage'],
            'bereichsleitung' => ['meals.view', 'meals.manage'],
            'mitarbeiter' => ['meals.view'],
            'lesen' => ['meals.view'],
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
            'version' => '0.6.0',
            'notes' => 'Essen und Speiseplan',
        ]);
    },
];
