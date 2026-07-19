<?php

declare(strict_types=1);

return [
    'id' => '2026_06_25_000021_ranks_bynames_progression',
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

        if (!$columnExists($pdo, 'persons', 'nickname')) {
            $pdo->exec("ALTER TABLE persons ADD COLUMN nickname VARCHAR(190) NULL AFTER display_name");
            $pdo->exec("ALTER TABLE persons ADD KEY idx_persons_nickname (nickname)");
        }

        if (!$columnExists($pdo, 'rank_levels', 'promotion_points_required')) {
            $pdo->exec("ALTER TABLE rank_levels ADD COLUMN promotion_points_required DECIMAL(8,2) NULL AFTER sort_order");
        }
        if (!$columnExists($pdo, 'rank_levels', 'next_rank_key')) {
            $pdo->exec("ALTER TABLE rank_levels ADD COLUMN next_rank_key VARCHAR(80) NULL AFTER promotion_points_required");
            $pdo->exec("ALTER TABLE rank_levels ADD KEY idx_rank_levels_next_rank_key (next_rank_key)");
        }
        if (!$columnExists($pdo, 'rank_levels', 'is_system_rank')) {
            $pdo->exec("ALTER TABLE rank_levels ADD COLUMN is_system_rank TINYINT(1) NOT NULL DEFAULT 0 AFTER next_rank_key");
        }

        if (!$columnExists($pdo, 'camp_person_statuses', 'next_rank_level_id')) {
            $pdo->exec("ALTER TABLE camp_person_statuses ADD COLUMN next_rank_level_id BIGINT UNSIGNED NULL AFTER rank_level_id");
            $pdo->exec("ALTER TABLE camp_person_statuses ADD KEY idx_camp_person_status_next_rank_level (next_rank_level_id)");
        }
        if (!$columnExists($pdo, 'camp_person_statuses', 'next_rank_label')) {
            $pdo->exec("ALTER TABLE camp_person_statuses ADD COLUMN next_rank_label VARCHAR(190) NULL AFTER next_rank_level_id");
        }
        if (!$columnExists($pdo, 'camp_person_statuses', 'promotion_status')) {
            $pdo->exec("ALTER TABLE camp_person_statuses ADD COLUMN promotion_status ENUM('offen','vorgeschlagen','bestaetigt','abgelehnt') NOT NULL DEFAULT 'offen' AFTER next_rank_label");
            $pdo->exec("ALTER TABLE camp_person_statuses ADD KEY idx_camp_person_status_promotion_status (promotion_status)");
        }
        if (!$columnExists($pdo, 'camp_person_statuses', 'promotion_note')) {
            $pdo->exec("ALTER TABLE camp_person_statuses ADD COLUMN promotion_note TEXT NULL AFTER promotion_status");
        }
        if (!$columnExists($pdo, 'camp_person_statuses', 'promotion_decided_at')) {
            $pdo->exec("ALTER TABLE camp_person_statuses ADD COLUMN promotion_decided_at DATETIME NULL AFTER promotion_note");
        }

        $ranks = [
            ['key' => 'knappe', 'label' => 'Knappe', 'sort' => 10, 'points' => 310, 'next' => 'ritter'],
            ['key' => 'ritter', 'label' => 'Ritter', 'sort' => 20, 'points' => 320, 'next' => 'freiherr'],
            ['key' => 'freiherr', 'label' => 'Freiherr', 'sort' => 30, 'points' => 330, 'next' => 'graf'],
            ['key' => 'graf', 'label' => 'Graf', 'sort' => 40, 'points' => 340, 'next' => 'markgraf'],
            ['key' => 'markgraf', 'label' => 'Markgraf', 'sort' => 50, 'points' => 350, 'next' => 'landgraf'],
            ['key' => 'landgraf', 'label' => 'Landgraf', 'sort' => 60, 'points' => 360, 'next' => 'fuerst'],
            ['key' => 'fuerst', 'label' => 'Fürst', 'sort' => 70, 'points' => 370, 'next' => 'herzog'],
            ['key' => 'herzog', 'label' => 'Herzog', 'sort' => 80, 'points' => 380, 'next' => 'grossherzog'],
            ['key' => 'grossherzog', 'label' => 'Großherzog', 'sort' => 90, 'points' => null, 'next' => null],
        ];

        $campYears = $pdo->query('SELECT id FROM camp_years')->fetchAll(PDO::FETCH_COLUMN);
        $insertRank = $pdo->prepare("INSERT INTO rank_levels
            (camp_year_id, key_name, label, sort_order, promotion_points_required, next_rank_key, is_system_rank, created_at, updated_at)
            VALUES (:camp_year_id, :key_name, :label, :sort_order, :promotion_points_required, :next_rank_key, 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                label = VALUES(label),
                sort_order = VALUES(sort_order),
                promotion_points_required = COALESCE(rank_levels.promotion_points_required, VALUES(promotion_points_required)),
                next_rank_key = VALUES(next_rank_key),
                is_system_rank = 1,
                updated_at = NOW()");

        foreach ($campYears as $campYearId) {
            foreach ($ranks as $rank) {
                $insertRank->execute([
                    'camp_year_id' => (int) $campYearId,
                    'key_name' => $rank['key'],
                    'label' => $rank['label'],
                    'sort_order' => $rank['sort'],
                    'promotion_points_required' => $rank['points'],
                    'next_rank_key' => $rank['next'],
                ]);
            }
        }

        $normalizeRank = static function (?string $value): ?string {
            $value = trim((string) $value);
            if ($value === '') {
                return null;
            }
            $value = preg_replace('/^\d+\s*/u', '', $value) ?? $value;
            $value = trim($value);
            $key = mb_strtolower($value, 'UTF-8');
            $key = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $key);
            $key = preg_replace('/[^a-z0-9]+/u', '', $key) ?? $key;
            return match ($key) {
                'knappe', 'kappe' => 'knappe',
                'ritter' => 'ritter',
                'freiherr' => 'freiherr',
                'graf' => 'graf',
                'markgraf' => 'markgraf',
                'landgraf' => 'landgraf',
                'fuerst', 'furst' => 'fuerst',
                'herzog' => 'herzog',
                'grossherzog', 'großherzog' => 'grossherzog',
                default => null,
            };
        };

        $selectStatus = $pdo->query('SELECT id, camp_year_id, rank_label FROM camp_person_statuses WHERE rank_label IS NOT NULL AND rank_label <> ""');
        $rankIdStmt = $pdo->prepare('SELECT id, label FROM rank_levels WHERE camp_year_id = :camp_year_id AND key_name = :key_name LIMIT 1');
        $updateStatus = $pdo->prepare('UPDATE camp_person_statuses SET rank_level_id = :rank_level_id, rank_label = :rank_label, updated_at = NOW() WHERE id = :id');
        foreach ($selectStatus->fetchAll(PDO::FETCH_ASSOC) as $status) {
            $key = $normalizeRank($status['rank_label'] ?? null);
            if ($key === null) {
                continue;
            }
            $rankIdStmt->execute(['camp_year_id' => (int) $status['camp_year_id'], 'key_name' => $key]);
            $rank = $rankIdStmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($rank)) {
                continue;
            }
            $updateStatus->execute([
                'id' => (int) $status['id'],
                'rank_level_id' => (int) $rank['id'],
                'rank_label' => (string) $rank['label'],
            ]);
        }

        $stmt = $pdo->prepare("UPDATE persons
            SET nickname = TRIM(SUBSTRING(internal_notes, 9)), updated_at = NOW()
            WHERE (nickname IS NULL OR nickname = '')
              AND internal_notes LIKE 'Beiname:%'");
        $stmt->execute();

        $stmt = $pdo->prepare("INSERT IGNORE INTO app_versions (version, applied_at, notes, created_at)
            VALUES (:version, NOW(), :notes, NOW())");
        $stmt->execute([
            'version' => '0.14.1',
            'notes' => 'Ränge, Beinamen und Rangvorschlag für Folgejahr',
        ]);
    },
];
