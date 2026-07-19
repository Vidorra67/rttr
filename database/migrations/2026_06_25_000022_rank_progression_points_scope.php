<?php

declare(strict_types=1);

return [
    'id' => '2026_06_25_000022_rank_progression_points_scope',
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

        if (!$columnExists($pdo, 'rank_levels', 'promotion_text')) {
            $pdo->exec("ALTER TABLE rank_levels ADD COLUMN promotion_text VARCHAR(255) NULL AFTER next_rank_key");
        }
        if (!$columnExists($pdo, 'rank_levels', 'is_permanent')) {
            $pdo->exec("ALTER TABLE rank_levels ADD COLUMN is_permanent TINYINT(1) NOT NULL DEFAULT 1 AFTER promotion_text");
        }
        if (!$columnExists($pdo, 'learning_units', 'unit_code')) {
            $pdo->exec("ALTER TABLE learning_units ADD COLUMN unit_code VARCHAR(80) NULL AFTER title");
            $pdo->exec("ALTER TABLE learning_units ADD KEY idx_learning_units_unit_code (unit_code)");
        }
        if (!$columnExists($pdo, 'learning_units', 'rank_level_id')) {
            $pdo->exec("ALTER TABLE learning_units ADD COLUMN rank_level_id BIGINT UNSIGNED NULL AFTER camp_year_id");
            $pdo->exec("ALTER TABLE learning_units ADD KEY idx_learning_units_rank_level (rank_level_id)");
        }

        $ranks = [
            ['knappe', 'Knappe', 10, 310, 'ritter', 'Von Knappe zum Ritter'],
            ['ritter', 'Ritter', 20, 320, 'freiherr', 'Von Ritter zum Freiherr'],
            ['freiherr', 'Freiherr', 30, 330, 'graf', 'Vom Freiherr zum Graf'],
            ['graf', 'Graf', 40, 340, 'markgraf', 'Vom Graf zum Markgraf'],
            ['markgraf', 'Markgraf', 50, 345, 'landgraf', 'Vom Markgraf zum Landgraf'],
            ['landgraf', 'Landgraf', 60, 350, 'fuerst', 'Vom Landgraf zum Fürst'],
            ['fuerst', 'Fürst', 70, 280, 'herzog', 'Vom Fürst zum Herzog'],
            ['herzog', 'Herzog', 80, null, 'grossherzog', 'Vom Herzog zum Großherzog'],
            ['grossherzog', 'Großherzog', 90, null, null, 'Höchster Rang'],
        ];

        $campYears = $pdo->query('SELECT id FROM camp_years')->fetchAll(PDO::FETCH_COLUMN);
        $rankStmt = $pdo->prepare("INSERT INTO rank_levels
            (camp_year_id, key_name, label, sort_order, promotion_points_required, next_rank_key, promotion_text, is_permanent, is_system_rank, created_at, updated_at)
            VALUES (:camp_year_id, :key_name, :label, :sort_order, :points, :next_rank_key, :promotion_text, 1, 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                label = VALUES(label),
                sort_order = VALUES(sort_order),
                promotion_points_required = VALUES(promotion_points_required),
                next_rank_key = VALUES(next_rank_key),
                promotion_text = VALUES(promotion_text),
                is_permanent = 1,
                is_system_rank = 1,
                updated_at = NOW()");

        foreach ($campYears as $campYearId) {
            foreach ($ranks as $rank) {
                [$key, $label, $sort, $points, $next, $text] = $rank;
                $rankStmt->execute([
                    'camp_year_id' => (int) $campYearId,
                    'key_name' => $key,
                    'label' => $label,
                    'sort_order' => $sort,
                    'points' => $points,
                    'next_rank_key' => $next,
                    'promotion_text' => $text,
                ]);
            }
        }

        $unitRows = [
            'knappe' => [['1. KnappeLE1', 'Knoten'], ['1. KnappeLE2', 'Natur'], ['1. KnappeLE3', 'Waldläufer']],
            'ritter' => [['2. RitterLE1', 'Wappen'], ['2. RitterLE2', 'Waffen'], ['2. RitterLE3', 'Feuer']],
            'freiherr' => [['3. FreiherrLE1', 'Küche'], ['3. FreiherrLE2', 'Lageraufbau'], ['3. FreiherrLE3', 'Erste Hilfe']],
            'graf' => [['4. GrafLE1', 'Abfrage'], ['4. GrafLE2', ''], ['4. GrafLE3', '']],
            'markgraf' => [['5. MarkgrafLE1', 'Abfrage'], ['5. MarkgrafLE2', ''], ['5. MarkgrafLE3', '']],
            'landgraf' => [['6. LandgrafLE1', ''], ['6. LandgrafLE2', ''], ['6. LandgrafLE3', '']],
            'fuerst' => [['7. FuerstLE1', 'Fürsprechung'], ['7. FuerstLE2', ''], ['7. FuerstLE3', '']],
            'herzog' => [['8. HerzogLE1', 'Ernennung durch Großherzöge']],
        ];

        $rankLookup = $pdo->prepare('SELECT id FROM rank_levels WHERE camp_year_id = :camp_year_id AND key_name = :key_name LIMIT 1');
        $unitStmt = $pdo->prepare("INSERT INTO learning_units
            (camp_year_id, rank_level_id, unit_code, title, category_key, responsible_label, sort_order, created_at, updated_at)
            VALUES (:camp_year_id, :rank_level_id, :unit_code, :title, 'lernen', NULL, :sort_order, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                rank_level_id = VALUES(rank_level_id),
                title = IF(learning_units.title = '' OR learning_units.title IS NULL, VALUES(title), learning_units.title),
                updated_at = NOW()");

        foreach ($campYears as $campYearId) {
            foreach ($unitRows as $rankKey => $units) {
                $rankLookup->execute(['camp_year_id' => (int) $campYearId, 'key_name' => $rankKey]);
                $rankId = $rankLookup->fetchColumn();
                if ($rankId === false) {
                    continue;
                }
                $sort = 10;
                foreach ($units as $unit) {
                    [$code, $title] = $unit;
                    if ($title === '') {
                        $title = $code;
                    }
                    $unitStmt->execute([
                        'camp_year_id' => (int) $campYearId,
                        'rank_level_id' => (int) $rankId,
                        'unit_code' => $code,
                        'title' => $title,
                        'sort_order' => $sort,
                    ]);
                    $sort += 10;
                }
            }
        }

        $categoryStmt = $pdo->prepare("INSERT INTO point_categories
            (key_name, label, scope, cadence, max_points_per_entry, max_entries_per_day, max_entries_per_camp, requires_slot, slot_options, description, is_staff_selectable, is_active, sort_order, created_at, updated_at)
            VALUES (:key_name, :label, :scope, :cadence, :max_points, :max_day, :max_camp, :requires_slot, :slot_options, :description, :staff, :active, :sort_order, NOW(), NOW())
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
            ['ordnung_persoenlich', 'Ordnung persönlich', 'person', 'twice_daily', 5, 2, null, 1, 'morgens,abends', 'Persönlich: Bett, Gepäck und eigene Ordnung.', 1, 1, 10],
            ['sauberkeit_geschirr', 'Sauberkeit Geschirr', 'person', 'twice_daily', 5, 2, null, 1, 'morgens,abends', 'Persönlich: Geschirr/Sauberkeit.', 1, 1, 20],
            ['zelt_ordnung', 'Ordnung Zelt', 'order', 'twice_daily', 5, 2, null, 1, 'morgens,abends', 'Global pro Orden/Zelt: Ordnung, Sauberkeit und Zustand des Zeltes.', 1, 1, 30],
            ['disziplin_puenktlichkeit', 'Disziplin und Pünktlichkeit', 'person', 'daily', 10, 1, null, 0, null, 'Persönlich: tägliche Bewertung je Teilnehmer.', 1, 1, 40],
            ['ordnung_pruefung_taeglich', 'Ordnung Prüfung', 'person', 'daily', 10, 1, null, 0, null, 'Persönlich: tägliche Prüfung, ob nichts vergessen wurde.', 1, 1, 50],
            ['spiel_wertung', 'Spiele', 'order', 'daily', 100, null, null, 0, null, 'Global pro Orden/Zelt: Spiel- und Wettbewerbswertung nach Platzierung.', 0, 1, 55],
            ['platzdienst', 'Platzdienst', 'order', 'camp_once', 5, null, 1, 0, null, 'Global pro Orden/Zelt: einmalige Lagerwertung.', 0, 1, 60],
            ['kuechendienst', 'Küchendienst', 'order', 'camp_limited', 3, null, 3, 1, 'einsatz_1,einsatz_2,einsatz_3', 'Global pro Orden/Zelt: bis zu 3 Einsätze mit je max. 3 Punkten.', 0, 1, 80],
            ['bonus_freizeit', 'Bonus Freizeit', 'person', 'daily', 5, null, null, 1, 'freizeit', 'Persönlich: Jeder Mitarbeiter kann pro Freizeit max. 5 Punkte an einen Teilnehmer vergeben.', 1, 1, 90],
        ];
        foreach ($categories as $category) {
            [$key, $label, $scope, $cadence, $maxPoints, $maxDay, $maxCamp, $requiresSlot, $slotOptions, $description, $staff, $active, $sort] = $category;
            $categoryStmt->execute([
                'key_name' => $key,
                'label' => $label,
                'scope' => $scope,
                'cadence' => $cadence,
                'max_points' => $maxPoints,
                'max_day' => $maxDay,
                'max_camp' => $maxCamp,
                'requires_slot' => $requiresSlot,
                'slot_options' => $slotOptions,
                'description' => $description,
                'staff' => $staff,
                'active' => $active,
                'sort_order' => $sort,
            ]);
        }

        $pdo->exec("UPDATE point_categories
            SET is_active = 0, is_staff_selectable = 0, updated_at = NOW()
            WHERE key_name IN ('zusatz_kuechendienst_1','zusatz_kuechendienst_2','zusatz_kuechendienst_3')");

        $stmt = $pdo->prepare("INSERT IGNORE INTO app_versions (version, applied_at, notes, created_at)
            VALUES (:version, NOW(), :notes, NOW())");
        $stmt->execute([
            'version' => '0.14.2',
            'notes' => 'Rangwechsel Folgejahr, permanente Ränge und Punktescopes präzisiert',
        ]);
    },
];
