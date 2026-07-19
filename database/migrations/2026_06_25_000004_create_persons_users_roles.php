<?php

declare(strict_types=1);

return [
    'id' => '2026_06_25_000004_create_persons_users_roles',
    'up' => static function (PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS persons (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            first_name VARCHAR(190) NOT NULL,
            last_name VARCHAR(190) NOT NULL,
            display_name VARCHAR(190) NOT NULL,
            birthdate DATE NULL,
            type_hint ENUM('teilnehmer','mitarbeiter','beides') NOT NULL DEFAULT 'mitarbeiter',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            deleted_at DATETIME NULL,
            KEY idx_persons_display_name (display_name),
            KEY idx_persons_active (is_active),
            KEY idx_persons_birthdate (birthdate)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            person_id BIGINT UNSIGNED NOT NULL,
            pin_hash VARCHAR(255) NOT NULL,
            is_login_enabled TINYINT(1) NOT NULL DEFAULT 1,
            failed_login_count INT UNSIGNED NOT NULL DEFAULT 0,
            locked_until DATETIME NULL,
            last_login_at DATETIME NULL,
            last_login_ip VARCHAR(64) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            UNIQUE KEY uq_users_person (person_id),
            KEY idx_users_login_enabled (is_login_enabled),
            KEY idx_users_locked_until (locked_until)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS roles (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            key_name VARCHAR(80) NOT NULL,
            label VARCHAR(190) NOT NULL,
            is_system TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            UNIQUE KEY uq_roles_key_name (key_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS role_permissions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            role_id BIGINT UNSIGNED NOT NULL,
            permission_key VARCHAR(120) NOT NULL,
            UNIQUE KEY uq_role_permissions_role_permission (role_id, permission_key),
            KEY idx_role_permissions_permission (permission_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS person_roles (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            person_id BIGINT UNSIGNED NOT NULL,
            role_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uq_person_roles_person_role (person_id, role_id),
            KEY idx_person_roles_person (person_id),
            KEY idx_person_roles_role (role_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NULL,
            person_id BIGINT UNSIGNED NULL,
            ip_address VARCHAR(64) NULL,
            user_agent VARCHAR(255) NULL,
            success TINYINT(1) NOT NULL DEFAULT 0,
            reason VARCHAR(80) NULL,
            created_at DATETIME NOT NULL,
            KEY idx_login_attempts_user (user_id),
            KEY idx_login_attempts_person (person_id),
            KEY idx_login_attempts_created (created_at),
            KEY idx_login_attempts_success (success)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $roles = [
            'admin' => 'Admin',
            'lagerleitung' => 'Lagerleitung',
            'bereichsleitung' => 'Bereichsleitung',
            'mitarbeiter' => 'Mitarbeiter',
            'lesen' => 'Nur Lesen',
        ];

        $stmt = $pdo->prepare("INSERT IGNORE INTO roles (key_name, label, is_system, created_at)
            VALUES (:key_name, :label, 1, NOW())");
        foreach ($roles as $key => $label) {
            $stmt->execute(['key_name' => $key, 'label' => $label]);
        }

        $permissions = [
            'admin' => ['dashboard.view', 'program.view', 'program.manage', 'meals.view', 'meals.manage', 'duties.view', 'duties.manage', 'persons.view', 'persons.manage', 'points.order.create', 'points.manage', 'imports.manage', 'settings.manage', 'backups.manage', 'audit.view'],
            'lagerleitung' => ['dashboard.view', 'program.view', 'program.manage', 'meals.view', 'meals.manage', 'duties.view', 'duties.manage', 'persons.view', 'persons.manage', 'points.order.create', 'points.manage', 'imports.manage', 'audit.view'],
            'bereichsleitung' => ['dashboard.view', 'program.view', 'program.manage', 'meals.view', 'meals.manage', 'duties.view', 'duties.manage', 'persons.view', 'points.order.create'],
            'mitarbeiter' => ['dashboard.view', 'program.view', 'meals.view', 'duties.view', 'persons.view', 'points.order.create'],
            'lesen' => ['dashboard.view', 'program.view', 'meals.view', 'duties.view', 'persons.view'],
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
            'version' => '0.2.0',
            'notes' => 'Personen, Rollen und PIN-Login',
        ]);
    },
];
