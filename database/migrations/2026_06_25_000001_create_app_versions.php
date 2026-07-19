<?php

declare(strict_types=1);

return [
    'id' => '2026_06_25_000001_create_app_versions',
    'up' => static function (PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS app_versions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            version VARCHAR(40) NOT NULL,
            applied_at DATETIME NOT NULL,
            notes TEXT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uq_app_versions_version (version)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $stmt = $pdo->prepare("INSERT IGNORE INTO app_versions (version, applied_at, notes, created_at)
            VALUES (:version, NOW(), :notes, NOW())");
        $stmt->execute([
            'version' => '0.1.0',
            'notes' => 'Projektgerüst und Baseline',
        ]);
    },
];
