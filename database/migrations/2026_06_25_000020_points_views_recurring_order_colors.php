<?php

declare(strict_types=1);

return [
    'id' => '2026_06_25_000020_points_views_recurring_order_colors',
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

        if (!$columnExists($pdo, 'orders', 'color_hex')) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN color_hex CHAR(7) NULL AFTER color_key");
        }

        if (!$columnExists($pdo, 'program_items', 'is_recurring')) {
            $pdo->exec("ALTER TABLE program_items ADD COLUMN is_recurring TINYINT(1) NOT NULL DEFAULT 0 AFTER is_visible");
        }
        if (!$columnExists($pdo, 'program_items', 'recurring_label')) {
            $pdo->exec("ALTER TABLE program_items ADD COLUMN recurring_label VARCHAR(190) NULL AFTER is_recurring");
        }

        $colorMap = [
            'blau' => '#2B49E0',
            'mint' => '#0FDFA0',
            'info' => '#2B49E0',
            'spiel' => '#07B383',
            'mahlzeit' => '#C77B12',
            'wache' => '#3B3F8F',
            'warnung' => '#D6452F',
        ];
        $stmt = $pdo->prepare("UPDATE orders SET color_hex = :color_hex WHERE (color_hex IS NULL OR color_hex = '') AND color_key = :color_key");
        foreach ($colorMap as $key => $hex) {
            $stmt->execute(['color_key' => $key, 'color_hex' => $hex]);
        }

        // Programmpunkte mit gleichem Titel an mehreren Lagertagen als wiederkehrend markieren.
        try {
            $pdo->exec("UPDATE program_items pi
                INNER JOIN (
                    SELECT camp_year_id, LOWER(TRIM(title)) AS normalized_title, COUNT(DISTINCT program_date) AS day_count
                    FROM program_items
                    WHERE deleted_at IS NULL AND is_visible = 1
                    GROUP BY camp_year_id, LOWER(TRIM(title))
                    HAVING day_count > 1
                ) recurring ON recurring.camp_year_id = pi.camp_year_id
                    AND recurring.normalized_title = LOWER(TRIM(pi.title))
                SET pi.is_recurring = 1,
                    pi.recurring_label = COALESCE(pi.recurring_label, 'wiederkehrend')
                WHERE pi.deleted_at IS NULL AND pi.is_visible = 1");
        } catch (Throwable) {
            // Falls ein Hosting sehr alte MySQL-Besonderheiten hat, darf die Markierung manuell erfolgen.
        }

        $stmt = $pdo->prepare("INSERT INTO point_categories
            (key_name, label, scope, cadence, max_points_per_entry, max_entries_per_day, max_entries_per_camp, requires_slot, slot_options, description, is_staff_selectable, is_active, sort_order, created_at, updated_at)
            VALUES (:key_name, :label, :scope, :cadence, :max_points_per_entry, :max_entries_per_day, :max_entries_per_camp, :requires_slot, :slot_options, :description, :is_staff_selectable, :is_active, :sort_order, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                label = VALUES(label),
                scope = VALUES(scope),
                cadence = VALUES(cadence),
                max_points_per_entry = VALUES(max_points_per_entry),
                max_entries_per_day = VALUES(max_entries_per_day),
                max_entries_per_camp = VALUES(max_entries_per_camp),
                requires_slot = VALUES(requires_slot),
                slot_options = VALUES(slot_options),
                description = VALUES(description),
                is_staff_selectable = VALUES(is_staff_selectable),
                is_active = VALUES(is_active),
                sort_order = VALUES(sort_order),
                updated_at = NOW()");
        $stmt->execute([
            'key_name' => 'spiel_wertung',
            'label' => 'Spielwertung',
            'scope' => 'order',
            'cadence' => 'daily',
            'max_points_per_entry' => 100,
            'max_entries_per_day' => null,
            'max_entries_per_camp' => null,
            'requires_slot' => 0,
            'slot_options' => null,
            'description' => 'Spiel- oder Wettbewerbswertung nach Platzierung. Eine Platzierung kann mehrere Orden haben.',
            'is_staff_selectable' => 0,
            'is_active' => 1,
            'sort_order' => 55,
        ]);

        $permissions = [
            'admin' => ['points.order.create', 'points.manage'],
            'lagerleitung' => ['points.order.create', 'points.manage'],
            'bereichsleitung' => ['points.order.create', 'points.manage'],
        ];
        $roleIdStmt = $pdo->prepare('SELECT id FROM roles WHERE key_name = :key_name LIMIT 1');
        $permissionStmt = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_key) VALUES (:role_id, :permission_key)");
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
            'version' => '0.14.0',
            'notes' => 'Wiederkehrende Programmpunkte, freie Ordensfarben und neue Punkte-Erfassungsansichten',
        ]);
    },
];
