<?php

declare(strict_types=1);

return [
    'id' => '2026_06_25_000024_rank_history_latest_known_fix',
    'up' => static function (PDO $pdo): void {
        $statuses = $pdo->query("SELECT cps.id, cps.person_id, cps.camp_year_id, cps.rank_label, cps.rank_level_id,
                rl.key_name, rl.label AS rank_level_label, rl.sort_order,
                cy.starts_on
            FROM camp_person_statuses cps
            INNER JOIN camp_years cy ON cy.id = cps.camp_year_id
            LEFT JOIN rank_levels rl ON rl.id = cps.rank_level_id
            ORDER BY cps.person_id ASC, cy.starts_on ASC, cy.id ASC, cps.id ASC")->fetchAll(PDO::FETCH_ASSOC);

        $normalizeRankKey = static function (?string $rankLabel): ?string {
            $value = trim((string) $rankLabel);
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

        $sortByKey = [
            'knappe' => 10,
            'ritter' => 20,
            'freiherr' => 30,
            'graf' => 40,
            'markgraf' => 50,
            'landgraf' => 60,
            'fuerst' => 70,
            'herzog' => 80,
            'grossherzog' => 90,
        ];

        $findRank = $pdo->prepare('SELECT id, label, sort_order FROM rank_levels WHERE camp_year_id = :camp_year_id AND key_name = :key_name LIMIT 1');
        $update = $pdo->prepare('UPDATE camp_person_statuses SET rank_label = :rank_label, rank_level_id = :rank_level_id, updated_at = NOW() WHERE id = :id');

        $highestByPerson = [];
        foreach ($statuses as $status) {
            $personId = (int) $status['person_id'];
            $key = (string) ($status['key_name'] ?? '');
            if ($key === '') {
                $key = $normalizeRankKey($status['rank_label'] ?? null) ?? '';
            }
            $sort = $key !== '' ? ($sortByKey[$key] ?? null) : null;

            if (isset($highestByPerson[$personId])) {
                $highest = $highestByPerson[$personId];
                if ($sort === null || (int) $highest['sort_order'] > (int) $sort) {
                    $findRank->execute(['camp_year_id' => (int) $status['camp_year_id'], 'key_name' => (string) $highest['key_name']]);
                    $level = $findRank->fetch(PDO::FETCH_ASSOC);
                    if (is_array($level)) {
                        $update->execute([
                            'id' => (int) $status['id'],
                            'rank_label' => (string) $level['label'],
                            'rank_level_id' => (int) $level['id'],
                        ]);
                    }
                    continue;
                }
            }

            if ($key !== '' && $sort !== null) {
                if (!isset($highestByPerson[$personId]) || $sort >= (int) $highestByPerson[$personId]['sort_order']) {
                    $highestByPerson[$personId] = ['key_name' => $key, 'sort_order' => $sort];
                }
            }
        }

        $stmt = $pdo->prepare("INSERT IGNORE INTO app_versions (version, applied_at, notes, created_at)
            VALUES (:version, NOW(), :notes, NOW())");
        $stmt->execute([
            'version' => '0.14.7',
            'notes' => 'Ranghistorie nutzt künftig den letzten bekannten beziehungsweise höchsten erreichten Rang und verhindert Rückstufungen.',
        ]);
    },
];
