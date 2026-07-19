<?php

declare(strict_types=1);

return [
    'id' => '2026_06_25_000017_import_cleanup_program_meals_duties',
    'up' => static function (PDO $pdo): void {
        try {
            $pdo->exec("UPDATE program_items
                SET starts_at = '13:00:00',
                    title = 'Mittagspause mit Kiosk, Schnitzen, Brennpeter und Specials',
                    category_key = 'freizeit',
                    updated_at = NOW()
                WHERE deleted_at IS NULL
                  AND title LIKE 'Danach:%'
                  AND (starts_at IS NULL OR starts_at <= '09:00:00')");
        } catch (Throwable) {
        }

        try {
            $pdo->exec("UPDATE program_items
                SET starts_at = '13:00:00', updated_at = NOW()
                WHERE deleted_at IS NULL
                  AND title LIKE '%Mittagspause%Kiosk%'
                  AND (starts_at IS NULL OR starts_at <= '09:00:00')");
        } catch (Throwable) {
        }

        try {
            $pdo->exec("UPDATE duties
                SET deleted_at = NOW(), updated_at = NOW()
                WHERE deleted_at IS NULL
                  AND (description = 'Import Aufgabenverteilung 2026'
                       OR title LIKE '<w:%'
                       OR title LIKE '%</w:%'
                       OR title LIKE '%w:val=%')");
        } catch (Throwable) {
        }

        try {
            $pdo->exec("UPDATE duty_types
                SET is_active = 0, updated_at = NOW()
                WHERE label LIKE '<w:%'
                   OR label LIKE '%</w:%'
                   OR label LIKE '%w:val=%'");
        } catch (Throwable) {
        }

        try {
            $pdo->exec("UPDATE meal_items mi
                JOIN camp_years cy ON cy.id = mi.camp_year_id
                SET mi.deleted_at = NOW(), mi.updated_at = NOW()
                WHERE mi.deleted_at IS NULL
                  AND mi.description = 'Import aus Speiseplan 2026'
                  AND (mi.meal_date < cy.starts_on OR mi.meal_date > cy.ends_on)");
        } catch (Throwable) {
        }

        try {
            $pdo->exec("UPDATE meal_items m
                JOIN meal_items older
                  ON older.camp_year_id = m.camp_year_id
                 AND older.meal_date = m.meal_date
                 AND older.meal_type = m.meal_type
                 AND older.deleted_at IS NULL
                 AND older.description = 'Import aus Speiseplan 2026'
                 AND older.id < m.id
                SET m.deleted_at = NOW(), m.updated_at = NOW()
                WHERE m.deleted_at IS NULL
                  AND m.description = 'Import aus Speiseplan 2026'");
        } catch (Throwable) {
        }

        $stmt = $pdo->prepare("INSERT IGNORE INTO app_versions (version, applied_at, notes, created_at)
            VALUES (:version, NOW(), :notes, NOW())");
        $stmt->execute([
            'version' => '0.13.8',
            'notes' => 'Importkorrekturen für DOCX-XML-Fragmente, Mittagspause, Speiseplan und kompaktere Diensteansicht',
        ]);
    },
];
