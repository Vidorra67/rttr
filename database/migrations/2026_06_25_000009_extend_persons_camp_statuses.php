<?php

declare(strict_types=1);

return [
    'id' => '2026_06_25_000009_extend_persons_camp_statuses',
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

        $columns = [
            'street' => "ALTER TABLE persons ADD COLUMN street VARCHAR(190) NULL AFTER type_hint",
            'zip' => "ALTER TABLE persons ADD COLUMN zip VARCHAR(20) NULL AFTER street",
            'city' => "ALTER TABLE persons ADD COLUMN city VARCHAR(190) NULL AFTER zip",
            'phone' => "ALTER TABLE persons ADD COLUMN phone VARCHAR(80) NULL AFTER city",
            'email' => "ALTER TABLE persons ADD COLUMN email VARCHAR(190) NULL AFTER phone",
            'emergency_contact_name' => "ALTER TABLE persons ADD COLUMN emergency_contact_name VARCHAR(190) NULL AFTER email",
            'emergency_contact_phone' => "ALTER TABLE persons ADD COLUMN emergency_contact_phone VARCHAR(80) NULL AFTER emergency_contact_name",
            'food_notes' => "ALTER TABLE persons ADD COLUMN food_notes TEXT NULL AFTER emergency_contact_phone",
            'allergy_notes' => "ALTER TABLE persons ADD COLUMN allergy_notes TEXT NULL AFTER food_notes",
            'medical_notes' => "ALTER TABLE persons ADD COLUMN medical_notes TEXT NULL AFTER allergy_notes",
            'internal_notes' => "ALTER TABLE persons ADD COLUMN internal_notes TEXT NULL AFTER medical_notes",
        ];

        foreach ($columns as $column => $sql) {
            if (!$columnExists($pdo, 'persons', $column)) {
                $pdo->exec($sql);
            }
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS camp_person_statuses (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            camp_year_id BIGINT UNSIGNED NOT NULL,
            person_id BIGINT UNSIGNED NOT NULL,
            is_participant TINYINT(1) NOT NULL DEFAULT 0,
            is_staff TINYINT(1) NOT NULL DEFAULT 0,
            participant_status ENUM('angemeldet','warteliste','abgemeldet','abgeschlossen') NOT NULL DEFAULT 'angemeldet',
            staff_status ENUM('aktiv','inaktiv','angefragt') NOT NULL DEFAULT 'aktiv',
            order_id BIGINT UNSIGNED NULL,
            rank_label VARCHAR(190) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            UNIQUE KEY uq_camp_person_status (camp_year_id, person_id),
            KEY idx_camp_person_status_camp (camp_year_id),
            KEY idx_camp_person_status_person (person_id),
            KEY idx_camp_person_status_order (order_id),
            KEY idx_camp_person_status_participant (is_participant),
            KEY idx_camp_person_status_staff (is_staff)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS person_guardians (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            person_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(190) NOT NULL,
            relation_label VARCHAR(80) NULL,
            phone VARCHAR(80) NULL,
            email VARCHAR(190) NULL,
            address_text TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            KEY idx_person_guardians_person (person_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $permissions = [
            'admin' => ['persons.sensitive.view'],
            'lagerleitung' => ['persons.sensitive.view'],
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
            'version' => '0.8.0',
            'notes' => 'Vollständige Teilnehmerdaten, Mitarbeiterstatus und Geburtstage',
        ]);
    },
];
