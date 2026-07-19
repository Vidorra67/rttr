<?php

declare(strict_types=1);

return [
    'id' => '2026_06_25_000008_create_duties',
    'up' => static function (PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS duty_types (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            key_name VARCHAR(80) NOT NULL,
            label VARCHAR(190) NOT NULL,
            icon_key VARCHAR(40) NULL,
            default_time_label VARCHAR(80) NULL,
            assignment_mode ENUM('person','order','mixed','label') NOT NULL DEFAULT 'mixed',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT UNSIGNED NOT NULL DEFAULT 100,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            UNIQUE KEY uq_duty_types_key (key_name),
            KEY idx_duty_types_active (is_active),
            KEY idx_duty_types_sort (sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS duties (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            camp_year_id BIGINT UNSIGNED NOT NULL,
            duty_date DATE NOT NULL,
            duty_type_id BIGINT UNSIGNED NOT NULL,
            starts_at TIME NULL,
            ends_at TIME NULL,
            time_label VARCHAR(80) NULL,
            title VARCHAR(190) NOT NULL,
            description TEXT NULL,
            status ENUM('offen','besetzt','erledigt','ausgefallen') NOT NULL DEFAULT 'offen',
            created_by BIGINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            deleted_at DATETIME NULL,
            KEY idx_duties_camp_date (camp_year_id, duty_date),
            KEY idx_duties_type (duty_type_id),
            KEY idx_duties_status (status),
            KEY idx_duties_deleted (deleted_at),
            KEY idx_duties_time (starts_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS duty_assignments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            duty_id BIGINT UNSIGNED NOT NULL,
            assignee_type ENUM('person','order','label') NOT NULL,
            person_id BIGINT UNSIGNED NULL,
            order_id BIGINT UNSIGNED NULL,
            label VARCHAR(190) NULL,
            created_at DATETIME NOT NULL,
            KEY idx_duty_assignments_duty (duty_id),
            KEY idx_duty_assignments_person (person_id),
            KEY idx_duty_assignments_order (order_id),
            KEY idx_duty_assignments_type (assignee_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS duty_rotation_rules (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            camp_year_id BIGINT UNSIGNED NOT NULL,
            duty_type_id BIGINT UNSIGNED NOT NULL,
            label VARCHAR(190) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            KEY idx_duty_rotation_rules_camp (camp_year_id),
            KEY idx_duty_rotation_rules_type (duty_type_id),
            KEY idx_duty_rotation_rules_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $types = [
            ['kuechendienst', 'K', 'Küchendienst', 'Küche', 'mixed', 10],
            ['spueldienst', 'S', 'Spüldienst', 'nach dem Essen', 'mixed', 20],
            ['platzdienst', 'P', 'Platzdienst', 'nach der Morgenrunde', 'order', 30],
            ['nachtwache', 'N', 'Nachtwache', '22:00–07:45', 'mixed', 40],
            ['lagerfeuerdienst', 'L', 'Lagerfeuerdienst', 'abends', 'person', 50],
            ['materialdienst', 'M', 'Materialdienst', '', 'mixed', 60],
            ['kiosk', 'K', 'Kiosk', 'Mittagspause', 'person', 70],
            ['feuerwart', 'F', 'Feuerwart', '', 'person', 80],
            ['flaggenwart', 'F', 'Flaggenwart', 'morgens/abends', 'person', 90],
            ['zeltwart', 'Z', 'Zeltwart', '', 'person', 100],
            ['sanitaetsdienst', 'S', 'Sanitätsdienst', '', 'person', 110],
            ['lagerwart', 'L', 'Lagerwart', '', 'person', 120],
        ];
        $stmt = $pdo->prepare("INSERT IGNORE INTO duty_types
            (key_name, icon_key, label, default_time_label, assignment_mode, is_active, sort_order, created_at, updated_at)
            VALUES (:key_name, :icon_key, :label, :default_time_label, :assignment_mode, 1, :sort_order, NOW(), NOW())");
        foreach ($types as $type) {
            [$key, $icon, $label, $time, $mode, $sort] = $type;
            $stmt->execute([
                'key_name' => $key,
                'icon_key' => $icon,
                'label' => $label,
                'default_time_label' => $time !== '' ? $time : null,
                'assignment_mode' => $mode,
                'sort_order' => $sort,
            ]);
        }

        $permissions = [
            'admin' => ['duties.view', 'duties.manage'],
            'lagerleitung' => ['duties.view', 'duties.manage'],
            'bereichsleitung' => ['duties.view', 'duties.manage'],
            'mitarbeiter' => ['duties.view'],
            'lesen' => ['duties.view'],
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
            'version' => '0.7.0',
            'notes' => 'Dienste und tägliche Aufgabenverteilung',
        ]);
    },
];
