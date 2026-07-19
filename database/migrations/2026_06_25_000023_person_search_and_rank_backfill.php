<?php

declare(strict_types=1);

return [
    'id' => '2026_06_25_000023_person_search_and_rank_backfill',
    'up' => static function (PDO $pdo): void {
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

        $rankSort = [
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

        $rankLabel = [
            'knappe' => 'Knappe',
            'ritter' => 'Ritter',
            'freiherr' => 'Freiherr',
            'graf' => 'Graf',
            'markgraf' => 'Markgraf',
            'landgraf' => 'Landgraf',
            'fuerst' => 'Fürst',
            'herzog' => 'Herzog',
            'grossherzog' => 'Großherzog',
        ];

        $findRankLevel = $pdo->prepare('SELECT id, label, sort_order FROM rank_levels WHERE camp_year_id = :camp_year_id AND key_name = :key_name LIMIT 1');
        $updateStatus = $pdo->prepare('UPDATE camp_person_statuses SET rank_label = :rank_label, rank_level_id = :rank_level_id, updated_at = NOW() WHERE id = :id');

        $statuses = $pdo->query("SELECT cps.id, cps.camp_year_id, cps.person_id, cps.rank_label, cps.rank_level_id,
                cy.starts_on,
                rl.key_name AS rank_key,
                rl.label AS rank_level_label,
                rl.sort_order AS rank_sort_order
            FROM camp_person_statuses cps
            INNER JOIN camp_years cy ON cy.id = cps.camp_year_id
            LEFT JOIN rank_levels rl ON rl.id = cps.rank_level_id
            ORDER BY cps.person_id ASC, cy.starts_on ASC, cy.id ASC, cps.id ASC")->fetchAll(PDO::FETCH_ASSOC);

        $highestByPerson = [];
        foreach ($statuses as $status) {
            $personId = (int) $status['person_id'];
            $currentKey = $status['rank_key'] !== null && $status['rank_key'] !== ''
                ? (string) $status['rank_key']
                : $normalizeRankKey($status['rank_label'] ?? null);
            $currentSort = $currentKey !== null ? ($rankSort[$currentKey] ?? null) : null;
            $target = null;

            if (isset($highestByPerson[$personId])) {
                $highest = $highestByPerson[$personId];
                if ($currentSort === null || (int) $highest['sort_order'] > (int) $currentSort) {
                    $target = $highest;
                }
            }

            if ($target === null && $currentKey !== null) {
                $target = [
                    'key' => $currentKey,
                    'label' => $rankLabel[$currentKey] ?? (string) ($status['rank_level_label'] ?? $status['rank_label'] ?? ''),
                    'sort_order' => $currentSort ?? 0,
                ];
            }

            if ($target !== null) {
                $findRankLevel->execute(['camp_year_id' => (int) $status['camp_year_id'], 'key_name' => $target['key']]);
                $level = $findRankLevel->fetch(PDO::FETCH_ASSOC);
                $rankLevelId = is_array($level) ? (int) $level['id'] : null;
                $label = is_array($level) ? (string) $level['label'] : (string) $target['label'];

                if ((string) ($status['rank_label'] ?? '') !== $label || (int) ($status['rank_level_id'] ?? 0) !== (int) ($rankLevelId ?? 0)) {
                    $updateStatus->execute([
                        'id' => (int) $status['id'],
                        'rank_label' => $label,
                        'rank_level_id' => $rankLevelId,
                    ]);
                }

                if (!isset($highestByPerson[$personId]) || (int) $target['sort_order'] >= (int) $highestByPerson[$personId]['sort_order']) {
                    $highestByPerson[$personId] = $target;
                }
            }
        }

        $version = $pdo->prepare("INSERT INTO app_versions (version, applied_at, notes, created_at)
            VALUES ('0.14.6', NOW(), 'Migration 000023 kompatibel mit app_versions.notes korrigiert', NOW())
            ON DUPLICATE KEY UPDATE notes = VALUES(notes)");
        $version->execute();
    },
];
