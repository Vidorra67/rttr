<?php

declare(strict_types=1);

return [
    'id' => '2026_06_25_000018_limit_meals_to_camp_days',
    'up' => static function (PDO $pdo): void {
        try {
            $pdo->exec("UPDATE meal_items mi
                INNER JOIN camp_years cy ON cy.id = mi.camp_year_id
                SET mi.deleted_at = COALESCE(mi.deleted_at, NOW()),
                    mi.updated_at = NOW()
                WHERE mi.deleted_at IS NULL
                  AND (mi.meal_date < cy.starts_on OR mi.meal_date > cy.ends_on)");
        } catch (Throwable) {
        }

        $stmt = $pdo->prepare("INSERT IGNORE INTO app_versions (version, applied_at, notes, created_at)
            VALUES (:version, NOW(), :notes, NOW())");
        $stmt->execute([
            'version' => '0.13.9',
            'notes' => 'Speiseplan wird strikt auf die Lagertage des jeweiligen Lagerjahres begrenzt.',
        ]);
    },
];
