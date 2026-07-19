# Manuelle Tests v0.13.0

## Migration

1. `php scripts/maintenance/migrate.php` ausführen.
2. Prüfen, ob Migration `2026_06_25_000014_seed_default_orders_and_icons` als ausgeführt markiert wurde.
3. In jedem vorhandenen Lagerjahr prüfen, ob diese Orden/Zelte vorhanden sind:
   - Johanniter
   - Falkner
   - Samariter
   - Petrusker
   - Morgensternritter
   - Malteser

## Google Icons

1. Einloggen.
2. Sidebar prüfen: alle Menüpunkte zeigen Material Symbols.
3. Mobile Ansicht prüfen: Bottom-Navigation zeigt Material Symbols.
4. Dienste prüfen: Dienstart-Icons zeigen Material Symbols.

## Importvorlagen

1. Als Admin oder Lagerleitung anmelden.
2. `Admin → Importe` öffnen.
3. Bereich „Mitgelieferte Vorlagen“ prüfen.
4. „6 Standardorden im aktiven Lagerjahr anlegen“ starten.
5. Importlauf öffnen und Ergebnis prüfen.
6. „Zeltlager Manager 2025 importieren“ starten.
7. Prüfen:
   - Lagerjahr 2025 wurde angelegt.
   - Personen wurden angelegt oder wiederverwendet.
   - Teilnehmerstatus wurde dem Lagerjahr zugeordnet.
   - Orden/Zelte wurden zugeordnet.
   - Ränge und Lerneinheiten wurden angelegt.
8. „Dummy-Lagerjahr 2000 aus 2025 erzeugen“ starten.
9. Prüfen, ob die gleichen Personen dem Lagerjahr 2000 zugeordnet wurden.
10. „Programm 2026 importieren“ starten.
11. Programmseite öffnen und Tagespunkte prüfen.
12. „Speiseplan 2026 importieren“ starten.
13. Essen-Seite öffnen und Mahlzeiten prüfen.
14. „Dienste und Aufgabenverteilung 2026 importieren“ starten.
15. Dienste-Seite öffnen und Dienstkarten prüfen.

## Sicherheit

1. Importvorlagen ohne Login aufrufen. Erwartung: Weiterleitung zu Login.
2. Mit Benutzer ohne `imports.manage` prüfen. Erwartung: Zugriff blockiert.
3. POST ohne CSRF prüfen. Erwartung: 403.
4. Direkter Browserzugriff auf `storage/import_sources/Zeltlager 2025 Manager.xlsx` prüfen. Erwartung: blockiert oder 404.

## Einschränkung

Die Importe übernehmen keine Excel-Formeln. Sie übernehmen nur strukturierte Startdaten.
