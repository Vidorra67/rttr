<?php

declare(strict_types=1);

return [
    'id' => '2026_06_25_000015_webdav_settings',
    'up' => static function (PDO $pdo): void {
        $settings = [
            ['webdav', 'enabled', '0', 0],
            ['webdav', 'base_url', '', 0],
            ['webdav', 'username', '', 0],
            ['webdav', 'password', '', 1],
            ['webdav', 'remote_base_path', 'ritterlager/backups', 0],
            ['webdav', 'timeout_seconds', '45', 0],
        ];
        $stmt = $pdo->prepare("INSERT IGNORE INTO settings (scope, key_name, value_text, is_secret, created_at, updated_at)
            VALUES (:scope, :key_name, :value_text, :is_secret, NOW(), NOW())");
        foreach ($settings as [$scope, $key, $value, $secret]) {
            $stmt->execute([
                'scope' => $scope,
                'key_name' => $key,
                'value_text' => $value,
                'is_secret' => $secret,
            ]);
        }

        $permissions = [
            'admin' => ['webdav.manage'],
            'lagerleitung' => ['webdav.manage'],
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
            'version' => '0.13.2',
            'notes' => 'WebDAV-Einstellungen, Testfunktion und Backup-Sync ergänzt. Browser-Migration mit Datenbankkonfigurationsformular verbessert.',
        ]);
    },
];
