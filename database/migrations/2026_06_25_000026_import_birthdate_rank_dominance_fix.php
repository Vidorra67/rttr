<?php

declare(strict_types=1);

return [
    'id' => '2026_06_25_000026_import_birthdate_rank_dominance_fix',
    'up' => static function (PDO $pdo): void {
        $tableExists = static function (PDO $pdo, string $table): bool {
            $stmt = $pdo->prepare("SELECT COUNT(*)
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table_name");
            $stmt->execute(['table_name' => $table]);
            return (int) $stmt->fetchColumn() > 0;
        };

        $columnExists = static function (PDO $pdo, string $table, string $column): bool {
            $stmt = $pdo->prepare("SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table_name
                  AND COLUMN_NAME = :column_name");
            $stmt->execute(['table_name' => $table, 'column_name' => $column]);
            return (int) $stmt->fetchColumn() > 0;
        };

        if ($tableExists($pdo, 'persons') && $columnExists($pdo, 'persons', 'birthdate')) {
            $pdo->exec("UPDATE persons
                SET birthdate = NULL, updated_at = NOW()
                WHERE birthdate IS NOT NULL
                  AND (birthdate < '1930-01-01' OR birthdate > CURRENT_DATE())");
        }

        if (!$tableExists($pdo, 'camp_person_statuses') || !$tableExists($pdo, 'rank_levels')) {
            return;
        }

        $statuses = $pdo->query("SELECT cps.id, cps.person_id, cps.camp_year_id, cps.rank_level_id, cps.rank_label,
                    COALESCE(rl.sort_order, 0) AS rank_sort_order
                FROM camp_person_statuses cps
                LEFT JOIN rank_levels rl ON rl.id = cps.rank_level_id
                WHERE cps.rank_level_id IS NOT NULL OR (cps.rank_label IS NOT NULL AND cps.rank_label <> '')
                ORDER BY cps.person_id ASC, COALESCE(rl.sort_order, 0) DESC, cps.id ASC")->fetchAll(PDO::FETCH_ASSOC);

        $highestByPerson = [];
        foreach ($statuses as $status) {
            $personId = (int) $status['person_id'];
            $sort = (int) ($status['rank_sort_order'] ?? 0);
            if (!isset($highestByPerson[$personId]) || $sort > (int) $highestByPerson[$personId]['rank_sort_order']) {
                $highestByPerson[$personId] = $status;
            }
        }

        $rankByKeyStmt = $pdo->prepare('SELECT id, label FROM rank_levels WHERE camp_year_id = :camp_year_id AND key_name = :key_name LIMIT 1');
        $rankKeyFromLabel = static function (?string $label): ?string {
            $value = trim((string) $label);
            if ($value === '') {
                return null;
            }
            $value = preg_replace('/^\d+\s*/u', '', $value) ?? $value;
            $value = mb_strtolower(trim($value), 'UTF-8');
            $value = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $value);
            $value = preg_replace('/[^a-z0-9]+/u', '', $value) ?? $value;
            return match ($value) {
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

        $update = $pdo->prepare("UPDATE camp_person_statuses
            SET rank_label = :rank_label,
                rank_level_id = :rank_level_id,
                updated_at = NOW()
            WHERE id = :id");

        foreach ($statuses as $status) {
            $personId = (int) $status['person_id'];
            $highest = $highestByPerson[$personId] ?? null;
            if ($highest === null) {
                continue;
            }
            $currentSort = (int) ($status['rank_sort_order'] ?? 0);
            $highestSort = (int) ($highest['rank_sort_order'] ?? 0);
            if ($highestSort <= 0 || $currentSort >= $highestSort) {
                continue;
            }

            $rankLabel = (string) ($highest['rank_label'] ?: $status['rank_label']);
            if ($rankLabel === '' && $highest['rank_level_id'] !== null) {
                $labelStmt = $pdo->prepare('SELECT label FROM rank_levels WHERE id = :id LIMIT 1');
                $labelStmt->execute(['id' => (int) $highest['rank_level_id']]);
                $rankLabel = (string) ($labelStmt->fetchColumn() ?: '');
            }

            $rankLevelId = null;
            $key = $rankKeyFromLabel($rankLabel);
            if ($key !== null) {
                $rankByKeyStmt->execute(['camp_year_id' => (int) $status['camp_year_id'], 'key_name' => $key]);
                $targetRank = $rankByKeyStmt->fetch(PDO::FETCH_ASSOC);
                if (is_array($targetRank)) {
                    $rankLevelId = (int) $targetRank['id'];
                    $rankLabel = (string) $targetRank['label'];
                }
            }
            $rankLevelId ??= $highest['rank_level_id'] !== null ? (int) $highest['rank_level_id'] : null;

            $update->execute([
                'id' => (int) $status['id'],
                'rank_label' => $rankLabel !== '' ? $rankLabel : null,
                'rank_level_id' => $rankLevelId,
            ]);
        }

        if ($tableExists($pdo, 'app_versions')) {
            $stmt = $pdo->prepare("INSERT INTO app_versions (version, applied_at, notes, created_at)
                VALUES ('0.14.10', NOW(), 'Migration 000026 kompatibel mit app_versions.applied_at korrigiert.', NOW())
                ON DUPLICATE KEY UPDATE applied_at = VALUES(applied_at), notes = VALUES(notes)");
            $stmt->execute();
        }
    },
];
