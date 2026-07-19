<?php

declare(strict_types=1);

return [
    'id' => '2026_06_25_000003_create_audit_log',
    'up' => static function (PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS audit_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NULL,
            action_key VARCHAR(120) NOT NULL,
            entity_type VARCHAR(80) NULL,
            entity_id BIGINT UNSIGNED NULL,
            ip_address VARCHAR(64) NULL,
            user_agent VARCHAR(255) NULL,
            details_json LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            KEY idx_audit_log_action (action_key),
            KEY idx_audit_log_user (user_id),
            KEY idx_audit_log_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
];
