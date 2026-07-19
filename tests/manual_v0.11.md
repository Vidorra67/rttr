# Manuelle Tests v0.11.0

## Vorbereitung

1. Updatepaket einspielen.
2. Migration ausführen: `php scripts/maintenance/migrate.php`.
3. Neu anmelden, damit `imports.manage` in der Session vorhanden ist.
4. Aktives Lagerjahr prüfen.

## Tests

- `/admin/importe` ohne Login muss auf `/login` leiten.
- Nutzer ohne `imports.manage` darf `/admin/importe` nicht öffnen.
- Admin oder Lagerleitung sieht den Menüpunkt `Importe`.
- Gültige XLSX-Datei hochladen.
- Gültige ODS-Datei hochladen.
- Gültige DOCX-Datei hochladen.
- Ungültige Datei, zum Beispiel PHP oder TXT, wird abgelehnt.
- Datei liegt nach Upload unter `storage/imports/YYYY/MM/`.
- Originaldateiname ist nicht der gespeicherte Dateiname.
- Importvorschau wird angezeigt.
- Importlauf wird in `import_runs` gespeichert.
- Fehler werden in `import_run_errors` gespeichert.
- Import ausführen.
- Bestehende Datensätze werden nicht überschrieben.
- CSRF-Manipulation bei Upload wird mit 403 blockiert.
- Direkter Browserzugriff auf `storage/imports/...` ist blockiert.
- Audit-Log enthält Vorschau und Ausführung.

## Hinweise

Wenn PHP-ZipArchive nicht installiert ist, wird die Datei zwar sicher gespeichert, aber nicht inhaltlich gelesen. Dann muss die PHP-Zip-Erweiterung am Hosting aktiviert werden.
