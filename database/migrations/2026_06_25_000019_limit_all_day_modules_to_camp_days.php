<?php

declare(strict_types=1);

return [
    'id' => '2026_06_25_000019_limit_all_day_modules_to_camp_days',
    'up' => static function (PDO $pdo): void {
        try {
            $pdo->exec("UPDATE program_items pi
                INNER JOIN camp_years cy ON cy.id = pi.camp_year_id
                SET pi.is_visible = 0,
                    pi.deleted_at = COALESCE(pi.deleted_at, NOW()),
                    pi.updated_at = NOW()
                WHERE pi.deleted_at IS NULL
                  AND (pi.program_date < cy.starts_on OR pi.program_date > cy.ends_on)");
        } catch (Throwable) {
            // Tabelle kann in alten/teilweise installierten Ständen fehlen. Migration bleibt idempotent.
        }

        try {
            $pdo->exec("UPDATE meal_items mi
                INNER JOIN camp_years cy ON cy.id = mi.camp_year_id
                SET mi.deleted_at = COALESCE(mi.deleted_at, NOW()),
                    mi.updated_at = NOW()
                WHERE mi.deleted_at IS NULL
                  AND (mi.meal_date < cy.starts_on OR mi.meal_date > cy.ends_on)");
        } catch (Throwable) {
        }

        try {
            $pdo->exec("UPDATE duties d
                INNER JOIN camp_years cy ON cy.id = d.camp_year_id
                SET d.deleted_at = COALESCE(d.deleted_at, NOW()),
                    d.updated_at = NOW()
                WHERE d.deleted_at IS NULL
                  AND (d.duty_date < cy.starts_on OR d.duty_date > cy.ends_on)");
        } catch (Throwable) {
        }

        try {
            $pdo->exec("UPDATE point_entries pe
                INNER JOIN camp_years cy ON cy.id = pe.camp_year_id
                SET pe.voided_at = COALESCE(pe.voided_at, NOW()),
                    pe.void_reason = COALESCE(NULLIF(pe.void_reason, ''), 'Automatisch storniert: Datum liegt außerhalb des Lagerzeitraums.')
                WHERE pe.voided_at IS NULL
                  AND (COALESCE(pe.scoring_date, DATE(pe.created_at)) < cy.starts_on
                       OR COALESCE(pe.scoring_date, DATE(pe.created_at)) > cy.ends_on)");
        } catch (Throwable) {
        }

        $stmt = $pdo->prepare("INSERT IGNORE INTO app_versions (version, applied_at, notes, created_at)
            VALUES (:version, NOW(), :notes, NOW())");
        $stmt->execute([
            'version' => '0.13.11',
            'notes' => 'Bugfix: Migration 000019 korrigiert. Lagertage-Begrenzung für Programm, Essen, Dienste und Ordnung.',
        ]);
    },
];
