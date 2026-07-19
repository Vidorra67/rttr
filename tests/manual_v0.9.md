# Manuelle Tests v0.9.0

## Migration

1. `php scripts/maintenance/migrate.php` ausführen.
2. Prüfen, ob `point_categories` und `point_entries` existieren.
3. Prüfen, ob Kategorie `ordnung` vorhanden ist.
4. Neu anmelden.

## Mitarbeiter-Flow

1. Als Mitarbeiter mit `points.order.create` anmelden.
2. `/ordnung` öffnen.
3. Teilnehmer suchen.
4. Kind auswählen.
5. -1 auswählen.
6. Grund eintragen.
7. Speichern.
8. Prüfen, ob Eintrag unter „Meine Einträge“ erscheint.
9. Prüfen, ob in der DB Kategorie `ordnung` verwendet wurde.

## Sicherheitsprüfung

1. POST auf `/ordnung/abziehen` ohne CSRF senden. Erwartung: 403.
2. POST mit manipulierter Kategorie senden. Erwartung: Kategorie wird nicht aus POST übernommen.
3. Als Mitarbeiter individuellen Wert senden. Erwartung: blockiert, außer Nutzer hat `points.manage`.
4. Ohne Login `/ordnung` öffnen. Erwartung: Weiterleitung zu `/login`.
5. Als Rolle ohne `points.order.create` öffnen. Erwartung: 403.

## Admin/Lagerleitung

1. Als Admin anmelden.
2. `/admin/ordnungspunkte` öffnen.
3. Filter nach Datum, Orden/Zelt und Person testen.
4. Eintrag stornieren.
5. Korrektur buchen.
6. Prüfen, dass Storno und Korrektur im Audit-Log auftauchen.

## XSS

1. Im Grundfeld `<script>alert(1)</script>` eintragen.
2. Listenansicht öffnen.
3. Erwartung: Text wird escaped ausgegeben, kein Script läuft.
