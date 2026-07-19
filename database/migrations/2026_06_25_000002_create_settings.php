<?php

declare(strict_types=1);

return [
    'id' => '2026_06_25_000002_create_settings',
    'up' => static function (PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            scope VARCHAR(80) NOT NULL DEFAULT 'app',
            key_name VARCHAR(120) NOT NULL,
            value_text TEXT NULL,
            value_json LONGTEXT NULL,
            is_secret TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            UNIQUE KEY uq_settings_scope_key (scope, key_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $stmt = $pdo->prepare("INSERT IGNORE INTO settings (scope, key_name, value_text, is_secret, created_at)
            VALUES (:scope, :key_name, :value_text, 0, NOW())");
        $stmt->execute([
            'scope' => 'app',
            'key_name' => 'app.name',
            'value_text' => 'Ritterlager Manager',
        ]);
    },
];
