<?php

declare(strict_types=1);

return [
    'id' => '2026_06_25_000016_refactor_order_points_scoring',
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
            'scope' => "ALTER TABLE point_categories ADD COLUMN scope ENUM('person','order') NOT NULL DEFAULT 'person' AFTER label",
            'cadence' => "ALTER TABLE point_categories ADD COLUMN cadence ENUM('daily','twice_daily','camp_once','camp_limited') NOT NULL DEFAULT 'daily' AFTER scope",
            'max_points_per_entry' => "ALTER TABLE point_categories ADD COLUMN max_points_per_entry INT UNSIGNED NOT NULL DEFAULT 0 AFTER cadence",
            'max_entries_per_day' => "ALTER TABLE point_categories ADD COLUMN max_entries_per_day INT UNSIGNED NULL AFTER max_points_per_entry",
            'max_entries_per_camp' => "ALTER TABLE point_categories ADD COLUMN max_entries_per_camp INT UNSIGNED NULL AFTER max_entries_per_day",
            'requires_slot' => "ALTER TABLE point_categories ADD COLUMN requires_slot TINYINT(1) NOT NULL DEFAULT 0 AFTER max_entries_per_camp",
            'slot_options' => "ALTER TABLE point_categories ADD COLUMN slot_options VARCHAR(255) NULL AFTER requires_slot",
            'description' => "ALTER TABLE point_categories ADD COLUMN description TEXT NULL AFTER slot_options",
        ];

        foreach ($columns as $column => $sql) {
            if (!$columnExists($pdo, 'point_categories', $column)) {
                $pdo->exec($sql);
            }
        }

        $entryColumns = [
            'scoring_date' => "ALTER TABLE point_entries ADD COLUMN scoring_date DATE NULL AFTER camp_year_id",
            'check_slot' => "ALTER TABLE point_entries ADD COLUMN check_slot VARCHAR(40) NULL AFTER source_type",
            'subject_label' => "ALTER TABLE point_entries ADD COLUMN subject_label VARCHAR(190) NULL AFTER check_slot",
            'max_points_at_entry' => "ALTER TABLE point_entries ADD COLUMN max_points_at_entry INT UNSIGNED NULL AFTER subject_label",
        ];

        foreach ($entryColumns as $column => $sql) {
            if (!$columnExists($pdo, 'point_entries', $column)) {
                $pdo->exec($sql);
            }
        }

        try {
            $pdo->exec("ALTER TABLE point_entries MODIFY COLUMN person_id BIGINT UNSIGNED NULL");
        } catch (Throwable) {
            // Wenn der Hoster die Änderung nicht braucht oder die Spalte bereits nullable ist, weiterlaufen.
        }

        try {
            $pdo->exec("ALTER TABLE point_entries MODIFY COLUMN source_type ENUM('ordnung_abzug','korrektur','import','system','bewertung','platzdienst','pruefung','kuechendienst') NOT NULL DEFAULT 'bewertung'");
        } catch (Throwable) {
            // Bestehende Installationen dürfen hier nicht blockieren. Die App nutzt nur Werte, die vorhanden sein sollen.
        }

        try {
            $pdo->exec("UPDATE point_entries SET scoring_date = DATE(created_at) WHERE scoring_date IS NULL");
        } catch (Throwable) {
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

        $categories = [
            ['ordnung_persoenlich', 'Ordnung persönlich', 'person', 'twice_daily', 5, 2, null, 1, 'morgens,abends', 'Persönliches Bett, Gepäck und eigene Ordnung. 2x täglich je max. 5 Punkte.', 1, 1, 10],
            ['sauberkeit_geschirr', 'Sauberkeit Geschirr', 'person', 'twice_daily', 5, 2, null, 1, 'morgens,abends', 'Geschirr und persönliche Sauberkeit. 2x täglich je max. 5 Punkte.', 1, 1, 20],
            ['zelt_ordnung', 'Zelt', 'order', 'twice_daily', 5, 2, null, 1, 'morgens,abends', 'Gesamtorden: Sauberkeit, Ordnung und Zustand des Zeltes. 2x täglich je max. 5 Punkte.', 1, 1, 30],
            ['disziplin_puenktlichkeit', 'Disziplin und Pünktlichkeit', 'person', 'daily', 10, 1, null, 0, null, 'Tägliche Bewertung je Teilnehmer, max. 10 Punkte.', 1, 1, 40],
            ['ordnung_pruefung_taeglich', 'Ordnung Prüfung', 'person', 'daily', 10, 1, null, 0, null, 'Tägliche Prüfung, ob nichts vergessen wurde. Max. 10 Punkte je Teilnehmer.', 1, 1, 50],
            ['platzdienst', 'Platzdienst', 'order', 'camp_once', 5, null, 1, 0, null, 'Einmalige Lagerpunkte für den gesamten Orden, optional nutzbar. Max. 5 Punkte.', 0, 1, 60],
            ['pruefung_fach_1', 'Prüfung Fach 1', 'person', 'camp_once', 30, null, 1, 0, null, 'Einmalige Prüfungspunkte je Teilnehmer. Max. 30 Punkte.', 0, 1, 70],
            ['pruefung_fach_2', 'Prüfung Fach 2', 'person', 'camp_once', 30, null, 1, 0, null, 'Einmalige Prüfungspunkte je Teilnehmer. Max. 30 Punkte.', 0, 1, 71],
            ['pruefung_fach_3', 'Prüfung Fach 3', 'person', 'camp_once', 30, null, 1, 0, null, 'Einmalige Prüfungspunkte je Teilnehmer. Max. 30 Punkte.', 0, 1, 72],
            ['zusatz_kuechendienst_1', 'Zusatz Küchendienst 1', 'person', 'camp_once', 3, null, 1, 0, null, 'Zusatzpunkte Küchendienst. Max. 3 Punkte.', 0, 1, 80],
            ['zusatz_kuechendienst_2', 'Zusatz Küchendienst 2', 'person', 'camp_once', 3, null, 1, 0, null, 'Zusatzpunkte Küchendienst. Max. 3 Punkte.', 0, 1, 81],
            ['zusatz_kuechendienst_3', 'Zusatz Küchendienst 3', 'person', 'camp_once', 3, null, 1, 0, null, 'Zusatzpunkte Küchendienst. Max. 3 Punkte.', 0, 1, 82],
        ];

        foreach ($categories as $category) {
            [$key, $label, $scope, $cadence, $maxPoints, $maxDay, $maxCamp, $requiresSlot, $slotOptions, $description, $staffSelectable, $active, $sort] = $category;
            $stmt->execute([
                'key_name' => $key,
                'label' => $label,
                'scope' => $scope,
                'cadence' => $cadence,
                'max_points_per_entry' => $maxPoints,
                'max_entries_per_day' => $maxDay,
                'max_entries_per_camp' => $maxCamp,
                'requires_slot' => $requiresSlot,
                'slot_options' => $slotOptions,
                'description' => $description,
                'is_staff_selectable' => $staffSelectable,
                'is_active' => $active,
                'sort_order' => $sort,
            ]);
        }

        $pdo->exec("UPDATE point_categories
            SET label = 'Ordnung alt', is_staff_selectable = 0, is_active = 0, updated_at = NOW()
            WHERE key_name = 'ordnung'");

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
            'version' => '0.13.3',
            'notes' => 'Ordnungspunkte in Bewertungsarten mit Tages- und Lagerlimits umgebaut',
        ]);
    },
];
