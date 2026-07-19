# Manuelle Tests v0.12.0

## Migration

- `php scripts/maintenance/migrate.php` ausführen.
- Tabellen `backup_runs`, `scheduled_tasks`, `scheduled_task_runs`, `webdav_sync_runs` prüfen.
- Rechte `backups.manage`, `backups.download`, `cron.manage`, `logs.view` prüfen.

## Backups

- Als Admin `/system/backups` öffnen.
- Manuelles Backup starten.
- Status `ok` oder nachvollziehbarer Fehler wird angezeigt.
- Datei liegt unter `storage/backups`.
- Download funktioniert nur mit `backups.download`.
- Direkter Browserzugriff auf Storage bleibt blockiert.

## Cron

- `/system/tasks` öffnen.
- Token regenerieren.
- Vollständige Cron-URL wird einmalig angezeigt.
- Aufgabe über „Jetzt testen“ starten.
- Lauf steht in `scheduled_task_runs`.
- CLI testen: `php scripts/cron/run_task.php cleanup_logs`.
- HTTP-Cron mit Token testen.
- Falscher Token wird abgelehnt.

## Logs

- `/system/logs` öffnen.
- Logdateien werden angezeigt.
- Logauszug zeigt keine Secrets.

## Rechte

- Nutzer ohne `backups.manage` kommt nicht auf Backups.
- Nutzer ohne `cron.manage` kommt nicht auf Aufgaben.
- Nutzer ohne `logs.view` kommt nicht auf Logs.
- Direkter POST ohne CSRF wird blockiert.
