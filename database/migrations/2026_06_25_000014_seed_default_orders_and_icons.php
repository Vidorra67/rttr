<?php

declare(strict_types=1);

return [
    'id' => '2026_06_25_000014_seed_default_orders_and_icons',
    'up' => static function (PDO $pdo): void {
        $orders = [
            ['Johanniter', 'JOH', 'blau', 10],
            ['Falkner', 'FAL', 'spiel', 20],
            ['Samariter', 'SAM', 'mint', 30],
            ['Petrusker', 'PET', 'mahlzeit', 40],
            ['Morgensternritter', 'MOR', 'wache', 50],
            ['Malteser', 'MAL', 'info', 60],
        ];

        $campYears = $pdo->query('SELECT id FROM camp_years')->fetchAll(PDO::FETCH_COLUMN);
        $insertOrder = $pdo->prepare("INSERT INTO orders
            (camp_year_id, name, short_name, color_key, sort_order, is_active, created_at, updated_at)
            VALUES (:camp_year_id, :name, :short_name, :color_key, :sort_order, 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE updated_at = updated_at");

        foreach ($campYears as $campYearId) {
            foreach ($orders as [$name, $shortName, $colorKey, $sortOrder]) {
                $insertOrder->execute([
                    'camp_year_id' => (int) $campYearId,
                    'name' => $name,
                    'short_name' => $shortName,
                    'color_key' => $colorKey,
                    'sort_order' => $sortOrder,
                ]);
            }
        }

        $iconUpdates = [
            'kuechendienst' => 'restaurant',
            'spueldienst' => 'cleaning_services',
            'platzdienst' => 'delete',
            'nachtwache' => 'dark_mode',
            'lagerfeuerdienst' => 'local_fire_department',
            'materialdienst' => 'inventory_2',
            'kiosk' => 'storefront',
            'feuerwart' => 'local_fire_department',
            'flaggenwart' => 'flag',
            'zeltwart' => 'camping',
            'sanitaetsdienst' => 'medical_services',
            'lagerwart' => 'inventory',
        ];
        $updateIcon = $pdo->prepare('UPDATE duty_types SET icon_key = :icon_key, updated_at = NOW() WHERE key_name = :key_name');
        foreach ($iconUpdates as $key => $icon) {
            $updateIcon->execute(['key_name' => $key, 'icon_key' => $icon]);
        }

        $stmt = $pdo->prepare("INSERT IGNORE INTO app_versions (version, applied_at, notes, created_at)
            VALUES (:version, NOW(), :notes, NOW())");
        $stmt->execute([
            'version' => '0.13.0',
            'notes' => 'Google Material Icons, Standardorden und mitgelieferte Importvorlagen',
        ]);
    },
];
