<?php

declare(strict_types=1);

return [
    'id' => '2026_06_25_000027_generate_recurring_duties',
    'up' => static function (PDO $pdo): void {
        $tableExists = static function (PDO $pdo, string $table): bool {
            $stmt = $pdo->prepare("SELECT COUNT(*)
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table_name");
            $stmt->execute(['table_name' => $table]);
            return (int) $stmt->fetchColumn() > 0;
        };

        if (!$tableExists($pdo, 'camp_years') || !$tableExists($pdo, 'duty_types') || !$tableExists($pdo, 'duties')) {
            return;
        }

        $findTypeIdByKey = static function (PDO $pdo, string $keyName): ?array {
            $stmt = $pdo->prepare('SELECT id, default_time_label FROM duty_types WHERE key_name = :key_name LIMIT 1');
            $stmt->execute(['key_name' => $keyName]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        };

        $ensureDuty = static function (PDO $pdo, int $campYearId, string $date, int $dutyTypeId, string $title, ?string $startsAt, ?string $timeLabel): int {
            $exists = $pdo->prepare('SELECT id FROM duties
                WHERE camp_year_id = :camp_year_id
                  AND duty_date = :duty_date
                  AND duty_type_id = :duty_type_id
                  AND title = :title
                  AND deleted_at IS NULL
                LIMIT 1');
            $exists->execute([
                'camp_year_id' => $campYearId,
                'duty_date' => $date,
                'duty_type_id' => $dutyTypeId,
                'title' => $title,
            ]);
            if ($exists->fetchColumn() !== false) {
                return 0;
            }

            $insert = $pdo->prepare("INSERT INTO duties
                (camp_year_id, duty_date, duty_type_id, starts_at, ends_at, time_label, title, description, status, created_by, updated_by, created_at, updated_at)
                VALUES (:camp_year_id, :duty_date, :duty_type_id, :starts_at, NULL, :time_label, :title, NULL, 'offen', NULL, NULL, NOW(), NOW())");
            $insert->execute([
                'camp_year_id' => $campYearId,
                'duty_date' => $date,
                'duty_type_id' => $dutyTypeId,
                'starts_at' => $startsAt !== null ? $startsAt . ':00' : null,
                'time_label' => $timeLabel,
                'title' => $title,
            ]);
            return 1;
        };

        $mealSlots = [
            ['label' => 'Frühstück', 'time' => '08:40'],
            ['label' => 'Mittagessen', 'time' => '12:30'],
            ['label' => 'Abendessen', 'time' => '18:00'],
        ];

        $platzdienstType = $findTypeIdByKey($pdo, 'platzdienst');
        $kuechendienstType = $findTypeIdByKey($pdo, 'kuechendienst');

        $created = 0;
        if ($platzdienstType !== null || $kuechendienstType !== null) {
            $campYears = $pdo->query('SELECT id, starts_on, ends_on FROM camp_years')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($campYears as $campYear) {
                $campYearId = (int) $campYear['id'];
                $start = new DateTimeImmutable((string) $campYear['starts_on'] . ' 00:00:00');
                $end = new DateTimeImmutable((string) $campYear['ends_on'] . ' 00:00:00');
                if ($end < $start) {
                    continue;
                }

                foreach (new DatePeriod($start, new DateInterval('P1D'), $end->modify('+1 day')) as $day) {
                    $date = $day->format('Y-m-d');

                    if ($platzdienstType !== null) {
                        $created += $ensureDuty(
                            $pdo,
                            $campYearId,
                            $date,
                            (int) $platzdienstType['id'],
                            'Platzdienst',
                            null,
                            $platzdienstType['default_time_label'] !== null && $platzdienstType['default_time_label'] !== ''
                                ? (string) $platzdienstType['default_time_label']
                                : null
                        );
                    }

                    if ($kuechendienstType !== null) {
                        foreach ($mealSlots as $meal) {
                            $created += $ensureDuty(
                                $pdo,
                                $campYearId,
                                $date,
                                (int) $kuechendienstType['id'],
                                'Küchendienst ' . $meal['label'],
                                $meal['time'],
                                $meal['label']
                            );
                        }
                    }
                }
            }
        }

        $stmt = $pdo->prepare("INSERT IGNORE INTO app_versions (version, applied_at, notes, created_at)
            VALUES (:version, NOW(), :notes, NOW())");
        $stmt->execute([
            'version' => '0.14.12',
            'notes' => 'Küchendienst (3x täglich, an Mahlzeiten orientiert) und Platzdienst (1x täglich) werden für bestehende und künftige Lagerjahre automatisch angelegt. Erzeugt: ' . $created,
        ]);
    },
];
